window.MAPPA_ESAMI_CONFIG = {
  schoolName: 'ITT Buonarroti Trento',
  title: 'ESAMI DI MATURITA\' 2026',
  idleHomeSeconds: 300,
  autoReloadSeconds: 300,
  floors: [
    { id: 'rialzato', label: 'Piano rialzato', image: 'assets/maps/rialzato.png' },
    { id: 'primo', label: 'Piano primo', image: 'assets/maps/primo.png' },
    { id: 'secondo', label: 'Piano secondo', image: 'assets/maps/secondo.png' },
    { id: 'terzo', label: 'Piano terzo', image: 'assets/maps/terzo.png' },
    { id: 'seminterrato', label: 'Piano seminterrato', image: 'assets/maps/seminterrato.png' }
  ],
  zones: [
    // NOTA: alcune zone sono spezzate in più rettangoli con lo stesso id.
    // Questo permette di seguire meglio la forma a L dei corridoi senza coprire mezza planimetria.

    // Piano rialzato
    { id: 'rialzato-ovest', floor: 'rialzato', name: 'Corridoio ovest', short: 'Ovest', x: 16.5, y: 53, w: 7.5, h: 24, color: 'blue' },
    { id: 'rialzato-ovest', floor: 'rialzato', name: 'Corridoio ovest', short: 'Ovest', x: 24, y: 72, w: 29, h: 8, color: 'blue' },
    { id: 'rialzato-nord', floor: 'rialzato', name: 'Corridoio nord / Atrio', short: 'Nord', x: 16, y: 25, w: 25, h: 13, color: 'red' },
    { id: 'rialzato-nord', floor: 'rialzato', name: 'Corridoio nord / Atrio', short: 'Atrio', x: 17, y: 38, w: 8, h: 17, color: 'red' },
    { id: 'rialzato-est', floor: 'rialzato', name: 'Corridoio est', short: 'Est', x: 41, y: 27, w: 20, h: 13, color: 'green' },
    { id: 'rialzato-sud', floor: 'rialzato', name: 'Corridoio sud', short: 'Sud', x: 63, y: 27, w: 24, h: 13, color: 'yellow' },

    // Piano primo
    { id: 'primo-ovest', floor: 'primo', name: 'Corridoio ovest', short: 'Ovest', x: 17.5, y: 47, w: 7.5, h: 27, color: 'blue' },
    { id: 'primo-ovest', floor: 'primo', name: 'Corridoio ovest', short: 'Ovest', x: 25, y: 72, w: 29, h: 8, color: 'blue' },
    { id: 'primo-nord', floor: 'primo', name: 'Corridoio nord', short: 'Nord', x: 3, y: 35, w: 25, h: 14, color: 'red' },
    { id: 'primo-nord', floor: 'primo', name: 'Corridoio nord', short: 'Nord', x: 16, y: 19, w: 10, h: 27, color: 'red' },
    { id: 'primo-est', floor: 'primo', name: 'Corridoio est', short: 'Est', x: 37, y: 29, w: 25, h: 12, color: 'green' },
    { id: 'primo-sud', floor: 'primo', name: 'Corridoio sud', short: 'Sud', x: 64, y: 30, w: 24, h: 12, color: 'yellow' },

    // Piano secondo
    { id: 'secondo-ovest', floor: 'secondo', name: 'Corridoio ovest', short: 'Ovest', x: 17.5, y: 46, w: 7.5, h: 28, color: 'blue' },
    { id: 'secondo-ovest', floor: 'secondo', name: 'Corridoio ovest', short: 'Ovest', x: 25, y: 72, w: 29, h: 8, color: 'blue' },
    { id: 'secondo-nord', floor: 'secondo', name: 'Corridoio nord', short: 'Nord', x: 3, y: 35, w: 25, h: 14, color: 'red' },
    { id: 'secondo-nord', floor: 'secondo', name: 'Corridoio nord', short: 'Nord', x: 16, y: 19, w: 10, h: 28, color: 'red' },
    { id: 'secondo-est', floor: 'secondo', name: 'Corridoio est', short: 'Est', x: 37, y: 29, w: 29, h: 12, color: 'green' },
    { id: 'secondo-sud', floor: 'secondo', name: 'Corridoio sud', short: 'Sud', x: 64, y: 30, w: 24, h: 12, color: 'yellow' },
    { id: 'secondo-aulamagna', floor: 'secondo', name: 'Aula Magna', short: 'Aula Magna', x: 15, y: 5, w: 17, h: 14, color: 'purple' },

    // Piano terzo
    { id: 'terzo-ovest', floor: 'terzo', name: 'Corridoio ovest', short: 'Ovest', x: 17.5, y: 46, w: 7.5, h: 28, color: 'blue' },
    { id: 'terzo-ovest', floor: 'terzo', name: 'Corridoio ovest', short: 'Ovest', x: 25, y: 72, w: 29, h: 8, color: 'blue' },
    { id: 'terzo-nord', floor: 'terzo', name: 'Corridoio nord', short: 'Nord', x: 3, y: 35, w: 25, h: 14, color: 'red' },
    { id: 'terzo-nord', floor: 'terzo', name: 'Corridoio nord', short: 'Nord', x: 16, y: 19, w: 10, h: 28, color: 'red' },
    { id: 'terzo-est', floor: 'terzo', name: 'Corridoio est', short: 'Est', x: 37, y: 29, w: 29, h: 12, color: 'green' },
    { id: 'terzo-sud', floor: 'terzo', name: 'Corridoio sud', short: 'Sud', x: 64, y: 30, w: 24, h: 12, color: 'yellow' },

    // Piano seminterrato
    { id: 'seminterrato-ovest', floor: 'seminterrato', name: 'Corridoio ovest', short: 'Ovest', x: 17.5, y: 51, w: 7.5, h: 26, color: 'blue' },
    { id: 'seminterrato-ovest', floor: 'seminterrato', name: 'Corridoio ovest', short: 'Ovest', x: 25, y: 72, w: 29, h: 8, color: 'blue' },
    { id: 'seminterrato-nord', floor: 'seminterrato', name: 'Zona nord', short: 'Nord', x: 2, y: 38, w: 24, h: 16, color: 'red' },
    { id: 'seminterrato-est', floor: 'seminterrato', name: 'Zona est', short: 'Est', x: 18, y: 9, w: 15, h: 15, color: 'green' },
    { id: 'seminterrato-est', floor: 'seminterrato', name: 'Zona est', short: 'Est', x: 20, y: 28, w: 37, h: 16, color: 'green' },
    { id: 'seminterrato-sud', floor: 'seminterrato', name: 'Corridoio sud', short: 'Sud', x: 58, y: 21, w: 31, h: 23, color: 'yellow' },
  ],
  masks: [
    { floor: 'rialzato', x: 78, y: 54, w: 12, h: 25 },
    { floor: 'primo', x: 78, y: 54, w: 12, h: 25 },
    { floor: 'secondo', x: 78, y: 54, w: 12, h: 25 },
    { floor: 'terzo', x: 78, y: 54, w: 12, h: 25 },
    { floor: 'seminterrato', x: 78, y: 54, w: 12, h: 25 }
  ],
  commissions: [
    {
      id: 'TNITIA001',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5DS',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. DS'
        },
        {
          name: '5INA',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. A'
        }
      ],
      zone: 'rialzato-est',
      building: 'Corpo centrale',
      floorText: 'Piano rialzato',
      roomText: 'Corridoio est'
    },
    {
      id: 'TNITIA002',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5INC',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. C'
        },
        {
          name: '5INB',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. B'
        }
      ],
      zone: 'terzo-nord',
      building: 'Corpo centrale',
      floorText: 'Piano terzo',
      roomText: 'Corridoio nord'
    },
    {
      id: 'TNITAT001',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5AUA',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "AUTOMAZIONE" SEZ. A'
        },
        {
          name: '5ELB',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "ELETTROTECNICA" SEZ. B'
        }
      ],
      zone: 'secondo-est',
      building: 'Corpo centrale',
      floorText: 'Piano secondo',
      roomText: 'Corridoio est'
    },
    {
      id: 'TNITET001',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5ELC',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "ELETTROTECNICA" SEZ. C'
        },
        {
          name: '5ELA',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "ELETTROTECNICA" SEZ. A'
        }
      ],
      zone: 'terzo-sud',
      building: 'Palazzina sud',
      floorText: 'Piano terzo',
      roomText: 'Corridoio sud - Aula 342'
    },
    {
      id: 'TNITMM002',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5MMB',
          course: 'MECCANICA, MECCATRONICA ED ENERGIA ARTICOLAZIONE "MECCANICA E MECCATRONICA" SEZ. B'
        },
        {
          name: '5MMA',
          course: 'MECCANICA, MECCATRONICA ED ENERGIA ARTICOLAZIONE "MECCANICA E MECCATRONICA" SEZ. A'
        }
      ],
      zone: 'primo-est',
      building: 'Corpo centrale',
      floorText: 'Piano primo',
      roomText: 'Corridoio est'
    },
    {
      id: 'TNITCA003',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CTB',
          course: ' COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. B'
        },
        {
          name: '5AUS',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "AUTOMAZIONE" SEZ. SERALE'
        },
        {
          name: '5CTS',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. SERALE'
        },
        {
          name: '5INS',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. SERALE'
        }
      ],
      zone: 'secondo-sud',
      building: 'Palazzina sud',
      floorText: 'Piano secondo',
      roomText: 'Corridoio sud'
    },
    {
      id: 'TNITCA004',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CTC',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. C'
        },
        {
          name: '5CTA',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. A'
        }
      ],
      zone: 'primo-nord',
      building: 'Corpo centrale',
      floorText: 'Piano primo',
      roomText: 'Corridoio nord'
    },
    {
      id: 'TNITBS001',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CSB',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. B'
        },
        {
          name: '5CSC',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. C'
        }
      ],
      zone: 'seminterrato-sud',
      building: 'Palazzina sud',
      floorText: 'Piano seminterrato',
      roomText: 'Palestra S63'
    },
    {
      id: 'TNITBS002',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CSD',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. D'
        },
        {
          name: '5CSA',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. A'
        }
      ],
      zone: 'seminterrato-sud',
      building: 'Palazzina sud',
      floorText: 'Piano seminterrato',
      roomText: 'Palestra S63'
    },
    {
      id: 'TNITBA001',
      prova: 'PRIMA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CBA',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE AMBIENTALI" SEZ. A'
        },
        {
          name: '5MEA',
          course: 'MECCANICA, MECCATRONICA ED ENERGIA ARTICOLAZIONE "ENERGIA" SEZ. A'
        },
        {
          name: '5CMA',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "CHIMICA E MATERIALI" SEZ. A'
        }
      ],
      zone: 'secondo-nord',
      building: 'Corpo centrale',
      floorText: 'Piano secondo',
      roomText: 'Corridoio nord'
    },
    {
      id: 'TNITIA001',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5DS',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. DS'
        },
        {
          name: '5INA',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. A'
        }
      ],
      zone: 'rialzato-est',
      building: 'Corpo centrale',
      floorText: 'Piano rialzato',
      roomText: 'Corridoio est'
    },
    {
      id: 'TNITIA002',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5INC',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. C'
        },
        {
          name: '5INB',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. B'
        }
      ],
      zone: 'terzo-nord',
      building: 'Corpo centrale',
      floorText: 'Piano terzo',
      roomText: 'Corridoio nord'
    },
    {
      id: 'TNITAT001',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5AUA',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "AUTOMAZIONE" SEZ. A'
        },
        {
          name: '5ELB',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "ELETTROTECNICA" SEZ. B'
        }
      ],
      zone: 'secondo-est',
      building: 'Corpo centrale',
      floorText: 'Piano secondo',
      roomText: 'Corridoio est'
    },
    {
      id: 'TNITET001',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5ELC',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "ELETTROTECNICA" SEZ. C'
        },
        {
          name: '5ELA',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "ELETTROTECNICA" SEZ. A'
        }
      ],
      zone: 'terzo-sud',
      building: 'Palazzina sud',
      floorText: 'Piano terzo',
      roomText: 'Corridoio sud - Aula 342'
    },
    {
      id: 'TNITMM002',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5MMB',
          course: 'MECCANICA, MECCATRONICA ED ENERGIA ARTICOLAZIONE "MECCANICA E MECCATRONICA" SEZ. B'
        },
        {
          name: '5MMA',
          course: 'MECCANICA, MECCATRONICA ED ENERGIA ARTICOLAZIONE "MECCANICA E MECCATRONICA" SEZ. A'
        }
      ],
      zone: 'primo-est',
      building: 'Corpo centrale',
      floorText: 'Piano primo',
      roomText: 'Corridoio est'
    },
    {
      id: 'TNITCA003',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CTB',
          course: ' COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. B'
        },
        {
          name: '5AUS',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "AUTOMAZIONE" SEZ. SERALE'
        },
        {
          name: '5CTS',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. SERALE'
        },
        {
          name: '5INS',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. SERALE'
        }
      ],
      zone: 'secondo-sud',
      building: 'Palazzina sud',
      floorText: 'Piano secondo',
      roomText: 'Corridoio sud - Aula 245'
    },
    {
      id: 'TNITCA004',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CTC',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. C'
        },
        {
          name: '5CTA',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. A'
        }
      ],
      zone: 'primo-ovest',
      building: 'Corpo centrale',
      floorText: 'Piano primo',
      roomText: 'Aule 104-105'
    },
    {
      id: 'TNITBS001',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CSB',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. B'
        },
        {
          name: '5CSC',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. C'
        }
      ],
      zone: 'seminterrato-sud',
      building: 'Palazzina sud',
      floorText: 'Piano seminterrato',
      roomText: 'Palestra S63'
    },
    {
      id: 'TNITBS002',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CSD',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. D'
        },
        {
          name: '5CSA',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. A'
        }
      ],
      zone: 'seminterrato-sud',
      building: 'Palazzina sud',
      floorText: 'Piano seminterrato',
      roomText: 'Palestra S63'
    },
    {
      id: 'TNITBA001',
      prova: 'SECONDA PROVA',
      anno: 'ESAMI DI MATURITA\' 2026',
      classes: [
        {
          name: '5CBA',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE AMBIENTALI" SEZ. A'
        },
        {
          name: '5MEA',
          course: 'MECCANICA, MECCATRONICA ED ENERGIA ARTICOLAZIONE "ENERGIA" SEZ. A'
        },
        {
          name: '5CMA',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "CHIMICA E MATERIALI" SEZ. A'
        }
      ],
      zone: 'secondo-nord',
      building: 'Corpo centrale',
      floorText: 'Piano secondo',
      roomText: 'Corridoio nord'
    },
    {
      id: 'TNITBA001',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'CHINETTI FRANCESCA',
      roomText: 'Aula commissione 204 - Aula orali 205',
      roomTextAulaCommissione: 'Aula 204',
      roomTextAulaOrali: 'Aula 205',
      zone: 'secondo-ovest',
      building: 'Corpo centrale',
      floorText: 'Corridoio Nord',
      classes: [
        {
          name: '5CBA', course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE AMBIENTALI" SEZ. A',
          commissioners: [
            { name: 'NICOLODI MARTINA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'MINUCCI TIZIANA', subject: 'Chimica Organica e Biochimica', type: 'Esterno' },
            { name: 'PINTACUDA SARA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'BARUCHELLI PIERGIORGIO', subject: 'Chimica Analitica e Strumentale', type: 'Interno' }
          ]
        },
        {
          name: '5MEA', course: 'MECCANICA, MECCATRONICA ED ENERGIA ARTICOLAZIONE "ENERGIA" SEZ. A',
          commissioners: [
            { name: 'NICOLODI MARTINA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'MORETTI TIZIANO', subject: 'Sistemi e Automazione', type: 'Esterno' },
            { name: 'PINTACUDA SARA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'BORTOLOTTI GABRIELE', subject: 'Meccanica, Macchine ed Energia', type: 'Interno' }
          ]
        },
        {
          name: '5CMA', course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "CHIMICA E MATERIALI" SEZ. A',
          commissioners: [
            { name: 'NICOLODI MARTINA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'MINUCCI TIZIANA', subject: 'Chimica Organica e Biochimica', type: 'Esterno' },
            { name: 'PINTACUDA SARA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'DELLA RICCA BERNARDO', subject: 'Chimica Analitica e Strumentale', type: 'Interno' }
          ]
        }
      ]
    },
    {
      id: 'TNITAT001',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'FANTASIA RITA',
      roomText: 'Aula commissione 225 - Aula orali 217',
      roomTextAulaCommissione: 'Aula 225',
      roomTextAulaOrali: 'Aula 217',
      zone: 'secondo-nord',
      building: 'Corpo centrale',
      floorText: 'Corridoio Nord',
      classes: [
        {    
          name: '5AUA',     
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "AUTOMAZIONE" SEZ. A',
          commissioners: [
            { name: 'MONACO AMEDEO', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'ZANONI ALESSANDRO', subject: 'Sistemi Automatici', type: 'Esterno' },
            { name: 'URTHALER DANIA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'FIGUNDIO FEDERICO', subject: 'Tecnologie e Progettazione di Sistemi Elettrici ed Elettronici', type: 'Interno' }
          ]
        },
        {
           name: '5ELB',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "ELETTROTECNICA" SEZ. B',
          commissioners: [
            { name: 'MONACO AMEDEO', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'ZANONI ALESSANDRO', subject: 'Elettronica ed Elettrotecnica', type: 'Esterno' },
            { name: 'LOPATRIELLO LUCIA IMMACOLATA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'PELLICANO\' GIUSEPPE', subject: 'Tecnologie e Progettazione di Sistemi Elettrici ed Elettronici', type: 'Interno' }
          ]
        }
        
      ]
    },
    {
      id: 'TNITET001',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'GHEDINA ENZA',
      roomText: 'Aula commissione 340 - Aula orali 342',
      roomTextAulaCommissione: 'Aula 340',
      roomTextAulaOrali: 'Aula 342',
      zone: 'terzo-sud',
      building: 'Palazzina sud',
      floorText: 'Corridoio Sud',
      classes: [
        {    
          name: '5ELC',     
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "ELETTROTECNICA" SEZ. C',
          commissioners: [
            { name: 'CONTE ROSSELLA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'MANGIA ELISA', subject: 'Elettronica ed Elettrotecnica', type: 'Esterno' },
            { name: 'BERNARDINATTI GIADA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'LEONARDELLI STEFANO', subject: 'Tecnologie e Progettazione di Sistemi Elettrici ed Elettronici', type: 'Interno' }
          ]
        },
        {
           name: '5ELA',
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "ELETTROTECNICA" SEZ. A',
          commissioners: [
            { name: 'CONTE ROSSELLA AMEDEO', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'MANGIA ELISA', subject: 'Elettronica ed Elettrotecnica', type: 'Esterno' },
            { name: 'LOPATRIELLO LUCIA IMMACOLATA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'LEONARDELLI STEFANO', subject: 'Tecnologie e Progettazione di Sistemi Elettrici ed Elettronici', type: 'Interno' }
          ]
        }
        
      ]
    },
    {
      id: 'TNITBS002',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'SPADACCINO TAMARA',
      roomText: 'Aula commissione R45 - Aula orali 139',
      roomTextAulaCommissione: 'Aula R45',
      roomTextAulaOrali: 'Aula 139',
      zone: 'rialzato-sud',
      building: 'Palazzina sud',
      floorText: 'Corridoio Est',
      classes: [
        {    
          name: '5CSD',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. D',
          commissioners: [
            { name: 'D\'AGOSTINO GABRIELLA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'BIANCO DONATELLA', subject: 'Igiene,Anatomia,Fisiologia,Patologia', type: 'Esterno' },
            { name: 'ATTANASIO LAURA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'FRIZZERA SILVIA', subject: 'Chimica Organica e Biochimica', type: 'Interno' }
          ]
        },
        {
           name: '5CSA',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. A',
          commissioners: [
            { name: 'D\'AGOSTINO GABRIELLA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'BIANCO DONATELLA', subject: 'Igiene,Anatomia,Fisiologia,Patologia', type: 'Esterno' },
            { name: 'MAGNAGUAGNO CARLO', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'FRIZZERA SILVIA', subject: 'Chimica Organica e Biochimica', type: 'Interno' }
          ]
        }
      ]
    },
     {
      id: 'TNITBS001',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'GABRIELLI SANDRA MARIA',
      roomText: 'Aula commissione 204 - Aula orali 205',
      roomTextAulaCommissione: 'Aula 204',
      roomTextAulaOrali: 'Aula 205',
      zone: 'secondo-ovest',
      building: 'Corpo centrale',
      floorText: 'Corridoio Ovest',
      classes: [
        {    
          name: '5CSB',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. B',
          commissioners: [
            { name: 'ZENI CINZIA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'COLANTUONO EMANUELA', subject: 'Igiene,Anatomia,Fisiologia,Patologia', type: 'Esterno' },
            { name: 'ATTANASIO LAURA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'SINIGOI LORIS', subject: 'Chimica Organica e Biochimica', type: 'Interno' }
          ]
        },
        {
           name: '5CSC',
          course: 'CHIMICA, MATERIALI E BIOTECNOLOGIE ARTICOLAZIONE "BIOTECNOLOGIE SANITARIE" SEZ. C',
          commissioners: [
            { name: 'ZENI CINZIA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'COLANTUONO EMANUELA', subject: 'Igiene,Anatomia,Fisiologia,Patologia', type: 'Esterno' },
            { name: 'CONTALDO ROBERTA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'DE LUCA DOMINGA', subject: 'Chimica Organica e Biochimica', type: 'Interno' }
          ]
        }
      ]
    },
     {
      id: 'TNITCA004',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'GIOVANNINI LUCA',
      roomText: 'Aula commissione 109 - Aula orali 207',
      roomTextAulaCommissione: 'Aula 109',
      roomTextAulaOrali: 'Aula 207',
      zone: 'primo-ovest',
      building: 'Corpo centrale',
      floorText: 'Corridoio Ovest',
      classes: [
        {    
          name: '5CTC',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. C',
          commissioners: [
            { name: 'BONIS ELISABETTA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'DI PIETRO ERIKA', subject: 'Lingua Inglese', type: 'Esterno' },
            { name: 'ECCHER ELISA', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'PATERNOSTER WALTER', subject: 'Topografia', type: 'Interno' }
          ]
        },
        {
           name: '5CTA',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. A',
          commissioners: [
            { name: 'BONIS ELISABETTA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'DI PIETRO ERIKA', subject: 'Lingua Inglese', type: 'Esterno' },
            { name: 'VALZOLGHER LUCA', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'PATERNOSTER WALTER', subject: 'Topografia', type: 'Interno' }
          ]
        }
      ]
    },
       {
      id: 'TNITCA003',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'ACQUISTI ANDREA',
      roomText: 'Aula commissione 256 - Aula orali 257',
      roomTextAulaCommissione: 'Aula 256',
      roomTextAulaOrali: 'Aula 257',
      zone: 'secondo-sud',
      building: 'Palazzina sud',
      floorText: 'Corridoio Sud',
      classes: [
        {    
          name: '5CTB',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. B',
          commissioners: [
            { name: 'COCCARELLI ANDREA', subject: 'Progettazione Costruzioni e Impianti', type: 'Esterno' },
            { name: 'VALENTINI GIOVANNA', subject: 'Lingua Inglese', type: 'Esterno' },
            { name: 'AUCIELLO GIULIO', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'CHIOGNA GUIDO', subject: 'Topografia', type: 'Interno' }
          ]
        },
        {
           name: '5CTS',
          course: 'COSTRUZIONI, AMBIENTE E TERRITORIO SEZ. SERALE',
          commissioners: [
            { name: 'COCCARELLI ANDREA', subject: 'Progettazione Costruzioni e Impianti', type: 'Esterno' },
            { name: 'VALENTINI GIOVANNA', subject: 'Lingua Inglese', type: 'Esterno' },
            { name: 'SCARPA ELISA', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'TEDESCO PIETRO', subject: 'Topografia', type: 'Interno' }
          ]
        },
                {    
          name: '5AUS',     
          course: 'ELETTRONICA ED ELETTROTECNICA ARTICOLAZIONE "AUTOMAZIONE" SEZ. SERALE',
          commissioners: [
            { name: 'FRATTON ANNAMARIA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'CORRADINI ALESSANDRO', subject: 'Sistemi Automatici', type: 'Esterno' },
            { name: 'BUCCI CONCETTA', subject: 'Lingua Inglese', type: 'Interno' },
            { name: 'LUNELLI RICCARDO', subject: 'Tecnologie e Progettazione di Sistemi Elettrici ed Elettronici', type: 'Interno' }
          ]
        },
        {    
          name: '5INS',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. SERALE',
          commissioners: [
            { name: 'MATTAREI DANIELA', subject: 'Sistemi e Reti', type: 'Esterno' },
            { name: 'VALENTINI GIOVANNA', subject: 'Lingua Inglese', type: 'Esterno' },
            { name: 'SCARPA ELISA', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'ARMANI PAOLO', subject: 'Informatica', type: 'Interno' }
          ]
        }
      ]
    },
    {
      id: 'TNITIA001',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'GENGA ADRIANO',
      roomText: 'Aula commissione R36 - Aula orali R41',
      roomTextAulaCommissione: 'Aula R36',
      roomTextAulaOrali: 'Aula R41',
      zone: 'rialzato-est',
      building: 'Corpo centrale',
      floorText: 'Corridoio Est',
      classes: [
        {    
          name: '5DS',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. DS',
          commissioners: [
            { name: 'ZAGO MANUEL', subject: 'Sistemi e Reti', type: 'Esterno' },
            { name: 'BENATTI ANDREA', subject: 'Lingua Inglese', type: 'Esterno' },
            { name: 'VALZOLGHER LUCA', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'RAFFONI LEONARDA', subject: 'Informatica', type: 'Interno' }
          ]
        },
    
        {    
          name: '5INA',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. A',
          commissioners: [
            { name: 'ZAGO MANUEL', subject: 'Sistemi e Reti', type: 'Esterno' },
            { name: 'BENATTI ANDREA', subject: 'Lingua Inglese', type: 'Esterno' },
            { name: 'ABBATE LORENZO', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'PESERICO GIULIA', subject: 'Informatica', type: 'Interno' }
          ]
        }
      ]
    },
     {
      id: 'TNITIA002',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'LOPARDO GIUSEPPA',
      roomText: 'Aula commissione 306 - Aula orali 305',
      roomTextAulaCommissione: 'Aula 306',
      roomTextAulaOrali: 'Aula 305',
      zone: 'terzo-ovest',
      building: 'Corpo centrale',
      floorText: 'Corridoio Ovest',
      classes: [
        {    
          name: '5INC',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. C',
          commissioners: [
            { name: 'PRIOLO BARBARA', subject: 'Sistemi e Reti', type: 'Esterno' },
            { name: 'BEBER GIOVANNA', subject: 'Lingua Inglese', type: 'Esterno' },
            { name: 'MARTINI STEFANO', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'ANDREATTA LAURA', subject: 'Informatica', type: 'Interno' }
          ]
        },
    
        {    
          name: '5INB',
          course: 'INFORMATICA E TELECOMUNICAZIONI ARTICOLAZIONE "INFORMATICA" SEZ. B',
          commissioners: [
            { name: 'PRIOLO BARBARA', subject: 'Sistemi e Reti', type: 'Esterno' },
            { name: 'BEBER GIOVANNA', subject: 'Lingua Inglese', type: 'Esterno' },
            { name: 'FUSCO SANDRA', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'ALBERGA TERESA', subject: 'Informatica', type: 'Interno' }
          ]
        }
      ]
    },
     {
      id: 'TNITMM002',
      prova: 'AULE COMMISSIONI',
      anno: 'ESAMI DI MATURITA\' 2026',
      president: 'VENDITTI LUIGI',
      roomText: 'Aula commissione 124a - Aula orali 122',
      roomTextAulaCommissione: 'Aula 124a',
      roomTextAulaOrali: 'Aula 122',
      zone: 'primo-est',
      building: 'Corpo centrale',
      floorText: 'Corridoio Est',
      classes: [
        {    
          name: '5MMB',
          course: 'MECCANICA, MECCATRONICA ED ENERGIA ARTICOLAZIONE "MECCANICA E MECCATRONICA" SEZ. B',
          commissioners: [
            { name: 'TRAMACERE MARIALUISA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'SAMUELE NOEMI', subject: 'Sistemi e Automazione', type: 'Esterno' },
            { name: 'MILITELLO ANGELO', subject: 'Meccanica, Macchine ed Energia', type: 'Interno' },
            { name: 'PINTACUDA SARA', subject: 'Lingua Inglese', type: 'Interno' }
          ]
        },
    
        {    
          name: '5MMA',
          course: 'MECCANICA, MECCATRONICA ED ENERGIA ARTICOLAZIONE "MECCANICA E MECCATRONICA" SEZ. A',
          commissioners: [
            { name: 'TRAMACERE MARIALUISA', subject: 'Lingua e Letteratura Italiana', type: 'Esterno' },
            { name: 'SAMUELE NOEMI', subject: 'Sistemi e Automazione', type: 'Esterno' },
            { name: 'SOLITO ALDO', subject: 'Lingua e Letteratura Italiana', type: 'Interno' },
            { name: 'CONTALDO ROBERTA', subject: 'Lingua Inglese', type: 'Interno' }
          ]
        }
      ]
    }
  ]
};
