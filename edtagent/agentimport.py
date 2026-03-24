import re
import json
import time
import shutil
import sys
import threading
import os
from datetime import datetime
from pathlib import Path

import pdfplumber
import requests
import pystray
from pystray import MenuItem as item
from PIL import Image
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler


def get_base_dir() -> Path:
    if getattr(sys, "frozen", False):
        return Path(sys.executable).resolve().parent
    return Path(__file__).resolve().parent

tray_ref = {"icon": None}
BASE_DIR = get_base_dir()

# ==============================
# CONFIG
# ==============================
CONFIG_PATH = BASE_DIR / "config.json"


def load_config():
    if not CONFIG_PATH.exists():
        raise FileNotFoundError(f"Config non trovato: {CONFIG_PATH}")
    with open(CONFIG_PATH, "r", encoding="utf-8") as f:
        return json.load(f)


CONFIG = load_config()

QUEUE_DIR = BASE_DIR / "queue"
SENT_DIR = BASE_DIR / "sent"
ERROR_DIR = BASE_DIR / "error"
LOGS_DIR = BASE_DIR / "logs"
ICON_DIR = BASE_DIR / "icon"
ICON_PATH = ICON_DIR / "gestore.ico"

API_URL = CONFIG.get("api_url")
SEND_TO_API = CONFIG.get("send_to_api", True)

HEADERS = {
    "Content-Type": "application/json; charset=utf-8"
}

TELEGRAM_ENABLED = CONFIG.get("telegram_enabled", False)
TELEGRAM_BOT_TOKEN = CONFIG.get("telegram_bot_token")
TELEGRAM_CHAT_ID = CONFIG.get("telegram_chat_id")

WATCH_EXTENSIONS = {".pdf"}

HTTP_SESSION = requests.Session()

SKIP_PREFIXES = (
    "Sostituzioni",
    "Dal ",
    "Docente Data",
    "Docente",
    "Data Docente Materia Classe Aula",
    "Sostituzione",
    "Attività sostituita",
    'I.T.T. "M. BUONARROTI"',
    "© Index Education",
)

ROW_RE = re.compile(
    r"""
    ^(?P<sostituto>[A-ZÀ-Ü'`.\-\s]+?)
    \s+
    (?P<data>\d{2}/\d{2})
    \s+dalle\s+
    (?P<ora_in>\d{2}h\d{2})
    \s+alle\s+
    (?P<ora_f>\d{2}h\d{2})
    \s+
    (?P<resto>.+)$
    """,
    re.VERBOSE,
)

# Soglie X da adattare se necessario dopo 1-2 test reali
COL_X_DOCENTE_SOSTITUTO_END = 360
COL_X_DATA_END = 635
COL_X_DOCENTE_SOSTITUITO_END = 980
COL_X_MATERIA_END = 1365
COL_X_CLASSE_END = 1510
# da qui in poi = aula
LOG_LEVEL = str(CONFIG.get("log_level", "INFO")).upper()
LOG_TO_CONSOLE = bool(CONFIG.get("log_to_console", True))
LOG_MAX_MB = float(CONFIG.get("log_max_mb", 5))
LOG_KEEP_FILES = int(CONFIG.get("log_keep_files", 10))

LOG_LEVELS = {
    "INFO": 10,
    "WARNING": 20,
    "ERROR": 30
}

stop_event = threading.Event()
observer_ref = {"observer": None}

def tray_status_text(_item):
    obs = observer_ref.get("observer")
    if stop_event.is_set():
        return "🔴 Stato: fermo"
    if obs is not None:
        return "🟢 Stato: attivo"
    return "🟡 Stato: avvio..."

def set_tray_title(text: str):
    icon = tray_ref.get("icon")
    if icon is not None:
        icon.title = text

def tray_open_sent(icon, menu_item):
    open_folder(SENT_DIR)

def tray_open_error(icon, menu_item):
    open_folder(ERROR_DIR)

# ==============================
# TRAY HELPERS
# ==============================
def open_folder(path: Path):
    try:
        os.startfile(str(path))
    except Exception as e:
        log_error(f"Impossibile aprire cartella {path}: {e}")


def tray_open_queue(icon, menu_item):
    open_folder(QUEUE_DIR)


def tray_open_logs(icon, menu_item):
    open_folder(LOGS_DIR)


def tray_send_test(icon, menu_item):
    log_info("Test Telegram richiesto da tray")
    send_telegram_message("🧪 Test manuale tray GestOre")


def tray_quit(icon, menu_item):
    log_info("Richiesta uscita da tray")
    send_telegram_message("🛑 Agent import SOSTITUZIONI fermato da tray")

    set_tray_title("🔴 Arresto in corso...")

    stop_event.set()

    obs = observer_ref.get("observer")
    if obs is not None:
        try:
            obs.stop()
        except Exception:
            pass
    time.sleep(1)
    icon.stop()


def create_tray_icon():
    if not ICON_PATH.exists():
        raise FileNotFoundError(f"Icona non trovata: {ICON_PATH}")
    image = Image.open(ICON_PATH)

    menu = pystray.Menu(
        item("📥 Apri queue (in arrivo)", tray_open_queue, default=True),
        item("✅ Apri sent (importati)", tray_open_sent),
        item("❌ Apri error", tray_open_error),
        item("📝 Apri logs", tray_open_logs),

        pystray.Menu.SEPARATOR,

        item("🧪 Invia test Telegram", tray_send_test),

        item(
            tray_status_text,
            lambda icon, item: None,
            enabled=False
        ),

        pystray.Menu.SEPARATOR,

        item("⏹ Esci", tray_quit),
    )

    icon = pystray.Icon("GestOreAgent", image, "GestOre Agent", menu)
    tray_ref["icon"] = icon
    return icon

# ==============================
# LOGGING
# ==============================
def current_log_level_value() -> int:
    return LOG_LEVELS.get(LOG_LEVEL, 10)


def should_log(level: str) -> bool:
    return LOG_LEVELS.get(level, 999) >= current_log_level_value()


def log_file_path() -> Path:
    return LOGS_DIR / f"agentimport_{datetime.now().strftime('%Y-%m-%d')}.log"


def rotate_logs_if_needed():
    try:
        log_path = log_file_path()
        if log_path.exists():
            max_bytes = int(LOG_MAX_MB * 1024 * 1024)
            if log_path.stat().st_size >= max_bytes:
                stamp = datetime.now().strftime("%H%M%S")
                rotated = log_path.with_name(f"{log_path.stem}_{stamp}{log_path.suffix}")
                log_path.rename(rotated)

        files = sorted(LOGS_DIR.glob("agentimport_*.log"), key=lambda p: p.stat().st_mtime, reverse=True)
        for old_file in files[LOG_KEEP_FILES:]:
            try:
                old_file.unlink()
            except Exception:
                pass
    except Exception:
        pass


def write_log(level: str, msg: str):
    if not should_log(level):
        return

    line = f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] [{level}] {msg}"

    if LOG_TO_CONSOLE:
        print(line)

    try:
        LOGS_DIR.mkdir(parents=True, exist_ok=True)
        rotate_logs_if_needed()
        with log_file_path().open("a", encoding="utf-8") as f:
            f.write(line + "\n")
    except Exception:
        pass


def log_info(msg: str):
    write_log("INFO", msg)


def log_warning(msg: str):
    write_log("WARNING", msg)


def log_error(msg: str):
    write_log("ERROR", msg)


# ==============================
# TELEGRAM
# ==============================
def send_telegram_message(text: str, max_retries: int = 4):
    if not TELEGRAM_ENABLED or not TELEGRAM_BOT_TOKEN or not TELEGRAM_CHAT_ID:
        return False

    url = f"https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/sendMessage"
    payload = {
        "chat_id": TELEGRAM_CHAT_ID,
        "text": text
    }
    headers = {
        "User-Agent": "GestOre-Agent/1.0",
        "Connection": "close"
    }

    last_error = None

    for attempt in range(1, max_retries + 1):
        try:
            resp = HTTP_SESSION.post(
                url,
                json=payload,
                headers=headers,
                timeout=(10, 25)
            )

            if resp.status_code >= 400:
                last_error = f"HTTP {resp.status_code}: {resp.text}"
                log_warning(f"Tentativo Telegram {attempt}/{max_retries} fallito: {last_error}")
            else:
                try:
                    data = resp.json()
                    if data.get("ok"):
                        msg_id = data.get("result", {}).get("message_id")
                        log_info(f"Telegram inviato correttamente, message_id={msg_id}")
                        return True
                except Exception:
                    log_info("Telegram inviato correttamente")
                    return True

        except requests.exceptions.RequestException as e:
            last_error = str(e)
            log_warning(f"Tentativo Telegram {attempt}/{max_retries} fallito: {e}")

        if attempt < max_retries:
            time.sleep(attempt * 2)

    log_error(f"Invio Telegram fallito definitivamente. Testo non inviato: {text}")
    log_error(f"Ultimo errore Telegram: {last_error}")
    return False


def now_hhmm() -> str:
    return datetime.now().strftime("%H:%M")


def telegram_import_success_message(file_name: str, record_count: int, warning_count: int) -> str:
    return (
        "📄 Sostituzioni importate\n"
        f"📎 {file_name}\n"
        f"✔️ {record_count} record\n"
        f"⚠️ {warning_count} warning\n"
        f"🕒 {now_hhmm()}"
    )


def telegram_error_message(file_name: str, error_text: str) -> str:
    return (
        "❌ Errore import sostituzioni\n"
        f"📎 {file_name}\n"
        f"🕒 {now_hhmm()}\n"
        f"{error_text}"
    )


# ==============================
# FILESYSTEM / PARSER
# ==============================
def ensure_dirs():
    for d in (QUEUE_DIR, SENT_DIR, ERROR_DIR, LOGS_DIR, ICON_DIR):
        d.mkdir(parents=True, exist_ok=True)


def normalize_space(s: str) -> str:
    return re.sub(r"\s+", " ", s or "").strip()


def hhmm_from_pdf(s: str) -> str:
    return s.replace("h", ":")


def is_skip_line(line: str) -> bool:
    line = normalize_space(line)
    if not line:
        return True

    skip_exact = {
        "Docente",
        "Data Docente Materia Classe Aula",
    }
    if line in skip_exact:
        return True

    return any(line.startswith(p) for p in SKIP_PREFIXES)

def parse_data_orario_cell(text: str):
    text = normalize_space(text)
    m = re.search(
        r'(?P<data>\d{2}/\d{2})\s+dalle\s+(?P<ora_in>\d{2}h\d{2})\s+alle\s+(?P<ora_f>\d{2}h\d{2})',
        text,
        re.IGNORECASE
    )
    if not m:
        return None

    return {
        "data": normalize_space(m.group("data")),
        "ora_inizio": hhmm_from_pdf(m.group("ora_in")),
        "ora_fine": hhmm_from_pdf(m.group("ora_f")),
    }


def extract_rows_from_pdf_table(pdf_path: str):
    rows_out = []

    with pdfplumber.open(pdf_path) as pdf:
        for page_num, page in enumerate(pdf.pages, start=1):
            table = page.extract_table()

            if not table:
                log_warning(f"Nessuna tabella trovata a pagina {page_num}")
                continue

            for raw_row in table:
                if not raw_row:
                    continue

                # normalizza numero colonne
                cells = [(normalize_space(c) if c is not None else "") for c in raw_row]

                # ci aspettiamo 6 colonne
                if len(cells) < 6:
                    continue

                docente_sostituto = cells[0]
                data_orario = cells[1]
                docente_sostituito = cells[2]
                materia = cells[3]
                classe = re.sub(r"\s*,\s*", ", ", cells[4])
                aula = re.sub(r"\s*,\s*", ", ", cells[5])

                full_line = normalize_space(" | ".join(cells))

                # salta intestazioni / righe vuote
                if (
                    docente_sostituto in ("", "Docente")
                    or data_orario in ("", "Data")
                    or docente_sostituito in ("", "Docente")
                    or materia in ("", "Materia")
                    or classe in ("", "Classe")
                    or aula in ("", "Aula")
                ):
                    continue

                parsed_dt = parse_data_orario_cell(data_orario)
                if not parsed_dt:
                    rows_out.append({
                        "error": {
                            "page": page_num,
                            "line": full_line,
                            "reason": "Data/orario non parsabile"
                        }
                    })
                    continue

                rec = {
                    "page": page_num,
                    "docente_sostituto": docente_sostituto,
                    "data": parsed_dt["data"],
                    "ora_inizio": parsed_dt["ora_inizio"],
                    "ora_fine": parsed_dt["ora_fine"],
                    "docente_sostituito": docente_sostituito,
                    "materia": materia,
                    "classe": classe,
                    "aula": aula,
                }

                rows_out.append({"record": rec})

    return rows_out

def norm_cell_text(parts):
    return normalize_space(" ".join(parts))


def normalize_date_orario_cell(text: str) -> str:
    text = normalize_space(text)
    text = text.replace("alleo", "alle 0")
    text = text.replace("alleO", "alle 0")
    text = text.replace("dalleO", "dalle 0")
    return text


def group_words_by_row(words, y_tol: float = 3.0):
    rows = []

    for w in words:
        txt = normalize_space(w.get("text", ""))
        if not txt:
            continue

        top = float(w.get("top", 0))

        found = None
        for row in rows:
            if abs(row["top"] - top) <= y_tol:
                found = row
                break

        if found is None:
            found = {"top": top, "words": []}
            rows.append(found)

        found["words"].append(w)

    rows.sort(key=lambda r: r["top"])

    for row in rows:
        row["words"].sort(key=lambda x: float(x.get("x0", 0)))

    return rows


def parse_row_from_words(page_num: int, words_in_row):
    col_doc_sostituto = []
    col_data = []
    col_doc_sostituito = []
    col_materia = []
    col_classe = []
    col_aula = []

    for w in words_in_row:
        text = normalize_space(w.get("text", ""))
        if not text:
            continue

        x0 = float(w.get("x0", 0))

        if x0 < COL_X_DOCENTE_SOSTITUTO_END:
            col_doc_sostituto.append(text)
        elif x0 < COL_X_DATA_END:
            col_data.append(text)
        elif x0 < COL_X_DOCENTE_SOSTITUITO_END:
            col_doc_sostituito.append(text)
        elif x0 < COL_X_MATERIA_END:
            col_materia.append(text)
        elif x0 < COL_X_CLASSE_END:
            col_classe.append(text)
        else:
            col_aula.append(text)

    docente_sostituto = norm_cell_text(col_doc_sostituto)
    data_orario = normalize_date_orario_cell(norm_cell_text(col_data))
    docente_sostituito = norm_cell_text(col_doc_sostituito)
    materia = norm_cell_text(col_materia)
    classe = re.sub(r"\s*,\s*", ", ", norm_cell_text(col_classe))
    aula = re.sub(r"\s*,\s*", ", ", norm_cell_text(col_aula))

    if not docente_sostituto and not data_orario and not docente_sostituito:
        return None

    full_line = normalize_space(
        f"{docente_sostituto} {data_orario} {docente_sostituito} {materia} {classe} {aula}"
    )

    if is_skip_line(full_line):
        return None

    m = ROW_RE.match(f"{docente_sostituto} {data_orario} {docente_sostituito}".strip())
    if not m:
        return {
            "error": {
                "page": page_num,
                "line": full_line,
                "reason": "ROW_RE no match su colonne"
            }
        }

    data = normalize_space(m.group("data"))
    ora_in = hhmm_from_pdf(m.group("ora_in"))
    ora_f = hhmm_from_pdf(m.group("ora_f"))

    if not materia:
        return {
            "error": {
                "page": page_num,
                "line": full_line,
                "reason": "Materia vuota"
            }
        }

    if not classe:
        return {
            "error": {
                "page": page_num,
                "line": full_line,
                "reason": "Classe vuota"
            }
        }

    if not aula:
        return {
            "error": {
                "page": page_num,
                "line": full_line,
                "reason": "Aula vuota"
            }
        }

    rec = {
        "page": page_num,
        "docente_sostituto": docente_sostituto,
        "data": data,
        "ora_inizio": ora_in,
        "ora_fine": ora_f,
        "docente_sostituito": docente_sostituito,
        "materia": materia,
        "classe": classe,
        "aula": aula,
    }

    log_info(
        "ROW COL OK | "
        f"sostituto=[{docente_sostituto}] | "
        f"data=[{data}] | ora=[{ora_in}-{ora_f}] | "
        f"sostituito=[{docente_sostituito}] | "
        f"materia=[{materia}] | classe=[{classe}] | aula=[{aula}]"
    )

    return {"record": rec}

def extract_lines_from_pdf(pdf_path: str):
    rows_out = []

    with pdfplumber.open(pdf_path) as pdf:
        for page_num, page in enumerate(pdf.pages, start=1):
            words = page.extract_words(
                x_tolerance=2,
                y_tolerance=3,
                keep_blank_chars=False,
                use_text_flow=False
            ) or []

            grouped_rows = group_words_by_row(words, y_tol=3.0)

            for row in grouped_rows:
                parsed = parse_row_from_words(page_num, row["words"])
                if parsed is None:
                    continue
                rows_out.append(parsed)

    return rows_out


def parse_pdf(pdf_path: str):
    records = []
    errors = []

    righe = extract_rows_from_pdf_table(pdf_path)

    for item in righe:
        if "record" in item:
            records.append(item["record"])
        elif "error" in item:
            errors.append(item["error"])

    return records, errors

def infer_year(ddmm: str) -> int:
    return datetime.today().year


def to_iso_date(ddmm: str) -> str:
    day, month = ddmm.split("/")
    year = infer_year(ddmm)
    return f"{year}-{month}-{day}"


def build_gestore_payload(records):
    items = []

    for r in records:
        items.append({
            "data": to_iso_date(r["data"]),
            "oraInizio": r["ora_inizio"],
            "oraFine": r["ora_fine"],
            "docenteSostituto": r["docente_sostituto"],
            "docenteSostituito": r["docente_sostituito"],
            "materia": r["materia"],
            "classe": r["classe"],
            "aula": r["aula"],
        })

    return {"items": items}


def send_to_gestore(payload):
    resp = HTTP_SESSION.post(
        API_URL,
        headers=HEADERS,
        data=json.dumps(payload, ensure_ascii=False).encode("utf-8"),
        timeout=600
    )
    return resp


def wait_file_ready(path: Path, checks: int = 3, delay: float = 1.0):
    stable_count = 0
    prev_size = -1

    while stable_count < checks:
        if not path.exists():
            raise FileNotFoundError(f"File non trovato: {path}")

        size = path.stat().st_size
        if size > 0 and size == prev_size:
            stable_count += 1
        else:
            stable_count = 0

        prev_size = size
        time.sleep(delay)


def write_sidecar_files(pdf_path: Path, records, errors, payload):
    out_parsed = pdf_path.with_suffix(".parsed.json")
    out_errors = pdf_path.with_suffix(".errors.json")
    out_payload = pdf_path.with_suffix(".gestore_payload.json")

    out_parsed.write_text(
        json.dumps(records, ensure_ascii=False, indent=2),
        encoding="utf-8"
    )
    out_errors.write_text(
        json.dumps(errors, ensure_ascii=False, indent=2),
        encoding="utf-8"
    )
    out_payload.write_text(
        json.dumps(payload, ensure_ascii=False, indent=2),
        encoding="utf-8"
    )

    return out_parsed, out_errors, out_payload


def move_with_sidecars(src_pdf: Path, dest_dir: Path):
    dest_dir.mkdir(parents=True, exist_ok=True)

    related = [
        src_pdf,
        src_pdf.with_suffix(".parsed.json"),
        src_pdf.with_suffix(".errors.json"),
        src_pdf.with_suffix(".gestore_payload.json"),
    ]

    moved = []
    for p in related:
        if p.exists():
            dest = dest_dir / p.name
            if dest.exists():
                stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                dest = dest_dir / f"{p.stem}_{stamp}{p.suffix}"
            shutil.move(str(p), str(dest))
            moved.append(dest)

    return moved


def process_pdf(pdf_path: Path):
    log_info(f"PROCESSING: {pdf_path.name}")

    records, errors = parse_pdf(str(pdf_path))
    payload = build_gestore_payload(records)

    out_parsed, out_errors, out_payload = write_sidecar_files(pdf_path, records, errors, payload)

    log_info(f"Record trovati: {len(records)}")
    log_info(f"Righe con errore: {len(errors)}")
    log_info(f"Creati: {out_parsed.name}, {out_errors.name}, {out_payload.name}")

    api_result = None

    if SEND_TO_API:
        resp = send_to_gestore(payload)
        content_type = resp.headers.get("Content-Type", "")

        try:
            body = resp.json() if "application/json" in content_type else resp.text
        except Exception:
            body = resp.text

        api_result = {
            "status_code": resp.status_code,
            "body": body
        }

        log_info(f"API HTTP {resp.status_code}")
        if isinstance(body, dict):
            log_info(f"API response ok={body.get('ok')} keys={list(body.keys())}")
        else:
            log_info(f"API response text={str(body)[:300]}")

        if resp.status_code >= 400:
            raise RuntimeError(f"Errore API HTTP {resp.status_code}: {body}")

        if isinstance(body, dict) and body.get("ok") is False:
            raise RuntimeError(f"Errore API applicativo: {body}")

    return {
        "records": records,
        "errors": errors,
        "payload": payload,
        "api_result": api_result
    }


# ==============================
# WATCHDOG HANDLER
# ==============================
class PDFHandler(FileSystemEventHandler):
    def __init__(self):
        super().__init__()
        self.processing = set()

    def on_created(self, event):
        self._handle(event)

    def on_moved(self, event):
        self._handle(event)

    def _handle(self, event):
        if event.is_directory:
            return

        src = getattr(event, "dest_path", None) or event.src_path
        path = Path(src)

        if path.suffix.lower() not in WATCH_EXTENSIONS:
            return

        if path.name.startswith("~"):
            return

        if path in self.processing:
            return

        set_tray_title(f"📄 Elaboro: {path.name}")

        self.processing.add(path)

        try:
            log_info(f"Nuovo file rilevato: {path.name}")
            wait_file_ready(path)

            result = process_pdf(path)
            target_dir = SENT_DIR

            warning_count = len(result["errors"])
            record_count = len(result["records"])

            if warning_count > 0:
                log_warning(
                    f"Import completato con {warning_count} righe non parse per il file {path.name}."
                )
            else:
                log_info(f"Import completato senza warning per il file {path.name}.")

            moved = move_with_sidecars(path, target_dir)
            for m in moved:
                log_info(f"Spostato: {m}")
            set_tray_title("🟢 GestOre Agent attivo")
            send_telegram_message(
                telegram_import_success_message(
                    file_name=path.name,
                    record_count=record_count,
                    warning_count=warning_count
                )
            )

        except Exception as e:
            set_tray_title("🔴 Errore import")
            err_msg = f"Errore su file {path.name}: {e}"
            log_error(err_msg)
            send_telegram_message(telegram_error_message(path.name, str(e)))
            try:
                moved = move_with_sidecars(path, ERROR_DIR)
                for m in moved:
                    log_error(f"Spostato in error: {m}")
            except Exception as e2:
                set_tray_title("🔴 Errore import")
                err_move_msg = f"Impossibile spostare in error {path.name}: {e2}"
                log_error(err_move_msg)
                send_telegram_message(
                    telegram_error_message(path.name, f"Impossibile spostare in error: {e2}")
                )
        finally:
            self.processing.discard(path)


# ==============================
# WATCHER
# ==============================
def process_existing_pdfs():
    existing = sorted([
        p for p in QUEUE_DIR.iterdir()
        if p.is_file() and p.suffix.lower() in WATCH_EXTENSIONS
    ])
    if not existing:
        return

    log_info(f"Trovati {len(existing)} PDF già presenti in queue/")
    handler = PDFHandler()
    for pdf in existing:
        class DummyEvent:
            is_directory = False
            src_path = str(pdf)
        handler._handle(DummyEvent())


def run_watcher():
    log_info("Config caricata correttamente")
    log_info(
        f"Config log: level={LOG_LEVEL}, console={LOG_TO_CONSOLE}, "
        f"max_mb={LOG_MAX_MB}, keep_files={LOG_KEEP_FILES}, send_to_api={SEND_TO_API}"
    )

    start_msg = "🚀 Agent import SOSTITUZIONI avviato e in ascolto sulla cartella queue"
    log_info(start_msg)
    send_telegram_message(start_msg)

    process_existing_pdfs()

    observer = Observer()
    observer_ref["observer"] = observer

    handler = PDFHandler()
    observer.schedule(handler, str(QUEUE_DIR), recursive=False)
    observer.start()
    set_tray_title("🟢 GestOre Agent attivo")

    try:
        while not stop_event.is_set():
            time.sleep(1)
    except Exception as e:
        set_tray_title("🔴 Errore import")
        crash_msg = f"❌ CRASH agent import SOSTITUZIONI\nErrore: {e}"
        log_error(crash_msg)
        send_telegram_message(crash_msg)
    finally:
        try:
            observer.stop()
        except Exception:
            pass
        observer.join()
        observer_ref["observer"] = None
        log_info("Watcher terminato")
        set_tray_title("🔴 GestOre Agent fermo")

def main():
    ensure_dirs()

    watcher_thread = threading.Thread(target=run_watcher, daemon=True)
    watcher_thread.start()

    tray_icon = create_tray_icon()
    tray_icon.run()


if __name__ == "__main__":
    main()