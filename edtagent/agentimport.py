import re
import json
import time
import shutil
from pathlib import Path
from datetime import datetime

import pdfplumber
import requests
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler

BASE_DIR = Path(__file__).resolve().parent

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

TAIL_RE = re.compile(
    r"""
    ^(?P<head>.+?)
    \s+
    (?P<materia>.+)
    \s+
    (?P<classe>[1-5][A-Z]+(?:[A-Z])?)
    \s+
    (?P<aula>[A-Z]?\d+[A-Z]?)$
    """,
    re.VERBOSE,
)

DOCENTE_RE = re.compile(r"^[A-ZÀ-Ü'`.\-]+(?:\s+[A-ZÀ-Ü'`.\-]+)+$")


LOG_LEVEL = str(CONFIG.get("log_level", "INFO")).upper()
LOG_TO_CONSOLE = bool(CONFIG.get("log_to_console", True))
LOG_MAX_MB = float(CONFIG.get("log_max_mb", 5))
LOG_KEEP_FILES = int(CONFIG.get("log_keep_files", 10))

LOG_LEVELS = {
    "INFO": 10,
    "WARNING": 20,
    "ERROR": 30
}


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

        # pulizia vecchi log
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
    write_log("ERROR", msg)


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


def ensure_dirs():
    for d in (QUEUE_DIR, SENT_DIR, ERROR_DIR, LOGS_DIR):
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


def split_docente_e_materia(resto: str):
    m = TAIL_RE.match(resto)
    if not m:
        return None

    head = normalize_space(m.group("head"))
    materia = normalize_space(m.group("materia"))
    classe = normalize_space(m.group("classe"))
    aula = normalize_space(m.group("aula"))

    combined = f"{head} {materia}".strip()
    parts = combined.split()

    for n in range(min(6, len(parts)), 1, -1):
        candidato_doc = " ".join(parts[:n])
        resto_materia = " ".join(parts[n:])

        if DOCENTE_RE.match(candidato_doc) and resto_materia:
            return {
                "docente_sostituito": normalize_space(candidato_doc),
                "materia": normalize_space(resto_materia),
                "classe": classe,
                "aula": aula,
            }

    if DOCENTE_RE.match(head):
        return {
            "docente_sostituito": head,
            "materia": materia,
            "classe": classe,
            "aula": aula,
        }

    return None


def extract_lines_from_pdf(pdf_path: str):
    righe = []
    with pdfplumber.open(pdf_path) as pdf:
        for page_num, page in enumerate(pdf.pages, start=1):
            text = page.extract_text() or ""
            for raw_line in text.splitlines():
                line = normalize_space(raw_line)
                if is_skip_line(line):
                    continue
                righe.append((page_num, line))
    return righe


def parse_pdf(pdf_path: str):
    records = []
    errors = []

    righe = extract_lines_from_pdf(pdf_path)

    for page_num, line in righe:
        m = ROW_RE.match(line)
        if not m:
            errors.append({
                "page": page_num,
                "line": line,
                "reason": "ROW_RE no match"
            })
            continue

        sostituto = normalize_space(m.group("sostituto"))
        data = normalize_space(m.group("data"))
        ora_in = hhmm_from_pdf(m.group("ora_in"))
        ora_f = hhmm_from_pdf(m.group("ora_f"))
        resto = normalize_space(m.group("resto"))

        detail = split_docente_e_materia(resto)
        if not detail:
            errors.append({
                "page": page_num,
                "line": line,
                "reason": "split_docente_e_materia failed"
            })
            continue

        rec = {
            "page": page_num,
            "docente_sostituto": sostituto,
            "data": data,
            "ora_inizio": ora_in,
            "ora_fine": ora_f,
            "docente_sostituito": detail["docente_sostituito"],
            "materia": detail["materia"],
            "classe": detail["classe"],
            "aula": detail["aula"],
        }
        records.append(rec)

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
        timeout=60
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

            send_telegram_message(
                telegram_import_success_message(
                    file_name=path.name,
                    record_count=record_count,
                    warning_count=warning_count
                )
            )

        except Exception as e:
            err_msg = f"Errore su file {path.name}: {e}"
            log_error(err_msg)
            send_telegram_message(telegram_error_message(path.name, str(e)))
            try:
                moved = move_with_sidecars(path, ERROR_DIR)
                for m in moved:
                    log_error(f"Spostato in error: {m}")
            except Exception as e2:
                err_move_msg = f"Impossibile spostare in error {path.name}: {e2}"
                log_error(err_move_msg)
                send_telegram_message(
                    telegram_error_message(path.name, f"Impossibile spostare in error: {e2}")
                )
        finally:
            self.processing.discard(path)


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


def main():
    ensure_dirs()
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
    handler = PDFHandler()
    observer.schedule(handler, str(QUEUE_DIR), recursive=False)
    observer.start()

    try:
        while True:
            time.sleep(1)

    except KeyboardInterrupt:
        stop_msg = "🛑 Agent import SOSTITUZIONI fermato manualmente"
        log_info(stop_msg)
        send_telegram_message(stop_msg)
        time.sleep(2)
        observer.stop()

    except Exception as e:
        crash_msg = f"❌ CRASH agent import SOSTITUZIONI\nErrore: {e}"
        log_error(crash_msg)
        send_telegram_message(crash_msg)
        observer.stop()

    finally:
        observer.join()


if __name__ == "__main__":
    main()