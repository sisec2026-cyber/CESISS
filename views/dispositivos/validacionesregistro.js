/**
 * Motor de clasificación de dispositivos CESISS
 * - La conexión en ALARMA es decisión del usuario
 * - El modelo NO auto-clasifica ALARMA
 * - Se prioriza estabilidad sobre automatización
 */
let selDet = null;
// ------------------------------------------------------------
//  Datos (catálogos)
// ------------------------------------------------------------
// SWITCHES
const modelosPorMarca = {
  CISCO: {
    poe: [
      "CBS250-8FPE-2G-NA","CBS250-16P2GNA","CBS350-24P-4G-NA","CBS250-24P-4GNA",
      "CBS250-24PP-4G-NA","CBS350-48FP-4G-NA","CBS250-24FP-4X-NA","C1200-24FP-4G",
      "C1200-16P-2G","C1200-24P-4G","C1200-16T-2G","C1200-24FP-4X"
    ],
    plano: ["CBS250-16-2G-NA","CBS350-24S-4G-NA","CBS250-24T-4G","CBS350-8S-E-2G-NA"]
  },
  PLANET: {
    poe: ["GS-4210-8P2S","GSD-1008HP","GS421016P4C","FSD-1008HP","FGSW-2624HPS","FGSD-1022VHP","GS-6311-24HP4X"],
    plano: ["NSW2020-24T1GT1GC-POE-IN","MGBSX1","GSD-803","XGS-6350-24X4C"]
  },
  "TP-LINK": { poe: ["TL-SG2210MP"], plano: ["TL-SG3428XF"] },
  PHYBRIDGE: { poe: ["NV-FLX-024-10G"] },
  "EXTREME NETWORKS": { poe: ["X435-24P-4S"] },
  GRANDSTREAM: { poe: ["GWN7802P"] },
  AXIS: { poe: ["T8524","T8516","T8508"] },
  ARUBA: { poe: ["R8Q67A ARUBA"] }
};

// CÁMARAS
const modelosPorMarcaCamara = {

  /* ===================== HANWHA / WISENET ===================== */
  HANWHA: {
    ip: [
      "QNV-6012R","QND-6082R","PNO-A9081R","QNV-6082R1","QND-8010R",
      "QNO-6012R","QNO-6082R","XNF-8010RVM","XNF-9010RV","PNM-7082RVD",
      "QND-6012R","QND-6022R1","XNF-8010RV","QND-8010R","XNF-9010RV",
      "PNE-9311R","XNV-6085R","XND-6085R","XNO-6085R"
    ],
    analogica: ["HCP-6320A","SCO-2080R"]
  },

  WISENET: {
    ip: [
      "XNV-6085","XND-6085","XNO-6085","QNV-7082R","QND-7082R"
    ],
    analogica: ["SDC-9441BC"]
  },

  /* ===================== AXIS ===================== */
  AXIS: {
    ip: [
      "F4105-LRE","M1135 Mk II","M1135-E Mk II","M1137-E Mk II",
      "P1375-E","P1377","P1455-LE","P1465-LE","P1467-LE","P1468-LE",
      "P3265-LVE","P3265V","P3267-LV","P3705-PLVE","P3735-PLE",
      "P3818-PVE","P4705-PLVE","P4708-PLVE","P5654-E Mk II","P5655-E",
      "M2035-LE","M2036-LE","M3007-PV","M3067-P","M3085-V","M3086-V",
      "M4317-PLVE","M4327-P","M4328-P","M1387-LE","P1387-LE"
    ],
    analogica: ["M7016","M7001"]
  },

  /* ===================== HIKVISION ===================== */
  HIKVISION: {
    ip: [
      "DS-2CD2023G2-I(U)","DS-2CD2125G0-IMS","DS-2CD2146G2-ISU",
      "DS-2CD2347G2-LU","DS-2CD2387G2-LSU","DS-2CD2T46G2-ISU",
      "DS-2CD2646G2-IZS","DS-2CD2746G2-IZS"
    ],
    analogica: [
      "DS-2CE12DF0T-F","DS-2CE16D0T-LFS","DS-2CE57D3T-VPITF",
      "DS-2CE16H0T-ITF","DS-2CE72DF3T-PIRXOS","DS-2CE10DF0T-PFS"
    ]
  },

  /* ===================== UNIVIEW ===================== */
  UNIVIEW: {
    ip: [
      "IPC322SB-DF28K-I0","IPC314SB-ADF28K-I0","IPC2122SB-ADF28KM-I0",
      "IPC2125LE-ADF28KM-G","IPC2325SB-DZK-I0","IPC3224SS-ADF28K-I1",
      "IPC324LE-DSF28K-G","IPC325SB-DF28K-I0","IPC3605SB-ADF16KM-I0",
      "IPC3612LB-ADF28K-H","IPC3612LB-SF28-A","IPC815SB-ADF14K-I0",
      "IPC86CEB-AF18KC-I0","IPC2K24SE-ADF40KMC-WL-I0"
    ],
    analogica: ["UAC-D122-AF28M-H","UAC-T112-F28"]
  },

  /* ===================== AVIGILON ===================== */
  AVIGILON: {
    ip: [
      "2.0C-H6M-D1","2.0C-H6A-D1","2.0C-H6SL-D1","3.0C-H6SL-BO2-IR",
      "6.0C-H6ADH-DO1-IR","8.0C-H6A-BO1-IR","8.0C-H6A-FE-DO1",
      "8.0C-H6A-FE-360-DO1-IR","12C-H5A-4MH-30","H4A-D1","H4A-PTZ"
    ],
    analogica: []
  },

  /* ===================== DAHUA ===================== */
  DAHUA: {
    ip: [
      "IPC-HDW2431T-AS","IPC-HFW2431T-ZS","IPC-HDW3541T-ZS",
      "IPC-HFW3849T1-AS-PV","SD49425XB-HNR","SD5A445XA-HNR"
    ],
    analogica: [
      "HAC-HFW1200R","HAC-HDW1200EMP","HAC-HDW1500TMQP",
      "HAC-HFW1500TL-A"
    ]
  },

  /* ===================== MERIVA ===================== */
  MERIVA: {
    ip: [
      "MIPC-2020","MIPC-2030","MIPC-3030","MIPC-4030"
    ],
    analogica: ["MSC-203","MSC-3214","MSC-120","MSC-130"]
  },

  /* ===================== SAMSUNG (LEGACY CCTV) ===================== */
  SAMSUNG: {
    ip: ["SNB-6004","SNV-6084"],
    analogica: ["SCO-2080R","SDC-7340BC"]
  },

  /* ===================== AMERICAN DYNAMICS ===================== */
  AmericanDynamics: {
    ip: [
      "Illustra Pro Gen4","Illustra Flex","Illustra Mini Dome"
    ],
    analogica: ["AD-720","AD-960"]
  },

  /* ===================== EPCOM / HILOOK ===================== */
  EPCOM: {
    ip: ["XD-14CLS","EPCOM-IP-30"],
    analogica: ["B50-TURBO-G2X(B)","B8-TURBO"]
  },

  HILOOK: {
    ip: ["IPC-B120","IPC-T221H"],
    analogica: ["THC-B120","THC-T120"]
  },

  /* ===================== BOSCH ===================== */
  BOSCH: {
    ip: [
      "NIN-70122","NIN-70130","DINION IP 7000","AUTODOME IP 5000"
    ],
    analogica: ["DINION 4000","FLEXIDOME"]
  },

  /* ===================== PELCO ===================== */
  PELCO: {
    ip: ["Sarix IME","Sarix IX","Spectra Pro"],
    analogica: ["Esprit","DF5"]
  }
};


// ==========================
// NVR
// ==========================
const modelosPorMarcaNVR = {

  HIKVISION: {
    nvr: [
      "DS-7604NI-Q1","DS-7608NI-Q2","DS-7616NI-Q2",
      "DS-7732NI","DS-7732NI-I4/16P","DS-7716NI-I4/16P",
      "DS-7608NI-I2","DS-7616NI-I2",
      "DS-9632NI-I8","DS-9664NI-I8",
      "DS7732NIM4/16P"
    ]
  },

  HANWHA: {
    nvr: [
      "XRN-410S","XRN-810S","XRN-820S","XRN-1610S",
      "XRN-1620SB1","XRN-2011","XRN-3010",
      "XRN-3210RB","XRN-6410RB"
    ]
  },

  DAHUA: {
    nvr: [
      "NVR4104HS-4KS2","NVR4216-4KS3",
      "DHI-NVR5208-8P-EI","DHI-NVR5216-16P-EI",
      "DHI-NVR5416-16P-EI","DHI-NVR5832-4KS2",
      "DHI-NVR608-128-4KS2"
    ]
  },

  UNIVIEW: {
    nvr: [
      "NVR301-04LS3-P4","NVR301-08S3-P8","NVR301-16LS3-P8",
      "NVR301-04S3-P4","NVR301-08LX-P8",
      "NVR302-08S2-P8","NVR302-16S2-P16","NVR302-32E2-IQ",
      "NVR302-16E2-P16-IQ","NVR302-16B-P16-IQ",
      "NVR304-16X","NVR304-32S-P16",
      "NVR516-128","NVR504-32E",
      "NV041UNV15"
    ]
  },

  WISENET: {
    nvr: [
      "PRN-4000","PRN-8000B","PRN-1600",
      "QRN-410","QRN-810","QRN-1630S"
    ]
  },

  AVIGILON: {
    nvr: [
      "AINVR-4","AINVR-8","AINVR-16",
      "AINVRPRM128TBNA","AINVR-ELITE"
    ]
  },

  MERIVA: {
    nvr: [
      "MVR-1004","MVR-1008","MVR-1016",
      "MVR-2008","MVR-2016"
    ]
  },

  EPCOM: {
    nvr: [
      "GABVID1R3","EPCOM-NVR-16","EPCOM-NVR-32"
    ]
  },

  BOSCH: {
    nvr: [
      "DIVAR IP 2000","DIVAR IP 3000","DIVAR IP 7000"
    ]
  },

  PELCO: {
    nvr: [
      "VideoXpert Core","VideoXpert Storage"
    ]
  }
};
// ------------------------------------------------------------

// ==========================
// DVR / XVR
// ==========================
const modelosPorMarcaDVR = {

  HIKVISION: {
    dvr: [
      "DS-7104HGHI","DS-7204HUHI","DS-7208HUHI",
      "DS-7216HUHI","DS-7308HUHI",
      "DS-7316HQHI","DS-8104HQHI"
    ]
  },

  DAHUA: {
    dvr: [
      "XVR5104","XVR5108","XVR5116",
      "XVR5216AN","XVR5432L",
      "XVR7108H","XVR7216AN"
    ]
  },

  HANWHA: {
    dvr: [
      "HRX-435","HRX-635","HRX-1635",
      "SRD-443","SRD-473","SRD-873"
    ]
  },

  ZKTECO: {
    dvr: [
      "Z8404XE","Z8108XE","Z8116XE"
    ]
  },

  SAMSUNG: {
    dvr: [
      "SRD-1653","SRD-853","SRD-473D"
    ]
  },

  MERIVA: {
    dvr: [
      "MDV-1004","MDV-1008","MDV-1016"
    ]
  },

  HILOOK: {
    dvr: [
      "DVR-204G-F1","DVR-208G-F1","DVR-216G-F1"
    ]
  },

  EPCOM: {
    dvr: [
      "EPCOM-DVR-4","EPCOM-DVR-8","EPCOM-DVR-16"
    ]
  }
};
// ------------------------------------------------------------
// UPS
const modelosPorMarcaUPS = {
  EAST: {
    ups: [
      "DS-AT-UPS650-LCD","DS-AT-UPS1000-LCD","DS-AT-UPS1500-LCD",
      "DS-AT-UPS2000-LCD","EA900","EA2000"
    ]
  },

  EPCOM: {
    ups: [
      "EPU850LCD","EPU1200LCD","EPU2000LCD",
      "EPU3000G3","EPU6000G3"
    ]
  },

  EATON: {
    ups: [
      "5E850USB","5E1500USB","5P1550","5P3000",
      "SMART1500LCD","9SX3000","9PX6000"
    ]
  },

  CYBERPOWER: {
    ups: [
      "UT-750GU","UT-1000GU","UT-1500GU",
      "CP1500AVRLCD","CP2200AVRLCD",
      "OL3000RTXL2U"
    ]
  },

  APC: {
    ups: [
      "BX650LI","BX1100LI","BR1500G",
      "SMT1500RM2U","SMT3000RM2U",
      "SRT5000XLI"
    ]
  },

  TRIPP_LITE: {
    ups: [
      "OMNI1000LCD","SMART1500LCD","SMART3000RM2U"
    ]
  }
};

// SERVIDORES
const modelosPorMarcaServidor = {

  DELL: {
    servidores: [
      "POWEREDGE R250","POWEREDGE R350","POWEREDGE R450",
      "POWEREDGE R550","POWEREDGE R650",
      "POWEREDGE T40","POWEREDGE T150","POWEREDGE T360",
      "POWER EDGE R550 XEON GOLD"
    ]
  },

  SUPERMICRO: {
    servidores: [
      "SYS-520P-WTR","SYS-620P-TR","SYS-720TQ-TR",
      "SYS-1029P-WTR","SYS-2029P-E1CR12L",
      "PWS-741P-1R"
    ]
  },

  AXIS: {
    servidores: [
      "S1296 96TB","S1296 192TB",
      "S1264 64TB","S1264 24TB",
      "S1132 32TB"
    ]
  },

  AVIGILON: {
    servidores: [
      "APP-500-8-DG A500 8TB",
      "APP-750-32-DG A750 32TB",
      "APP-1500-64-DG A1500 64TB"
    ]
  },

  LIAS: {
    servidores: [
      "AWA-CLD-3Y","AWA-CLD-5Y"
    ]
  },

  HPE: {
    servidores: [
      "PROLIANT DL20","PROLIANT DL160",
      "PROLIANT DL360","PROLIANT DL380",
      "MICROSERVERS GEN10"
    ]
  }
};


// ------------------------------------------------------------
// DISPOSITIVOS DE ALARMA Y CONTROL
// ------------------------------------------------------------

// Detectores de humo (DH)
const modelosPorMarcaDH = {
  DMP: {
    alambrico: ["1046747","1164NS-W","266-1012"],
    inalambrico: ["1100DH","1114"]
  },

  SYSTEM_SENSOR: {
    alambrico: ["2W-B","4WTA-B","COSMOD2W"],
    inalambrico: []
  },

  HONEYWELL: {
    alambrico: ["5806W3","5808W3"],
    inalambrico: ["5808W3"]
  }
};


// PIRS
const modelosPorMarcaPIR = {
  HONEYWELL: {
    alambrico: ["IS335","DT7450","DT8035"],
    inalambrico: ["5800PIR","5800PIR-RES","IS335T"]
  },

  DSC: {
    alambrico: ["LC-100-PI","LC-104-PIMW","LC-200"],
    inalambrico: ["WS4904P","PG9303","PG9904"]
  },

  BOSCH: {
    alambrico: ["ISC-BPR2-W12","ISC-BDL2-WP12","ISC-BPQ2-W12"],
    inalambrico: []
  },

  DMP: {
    alambrico: ["1046747","1164NS-W"],
    inalambrico: ["1100PIR","1125"]
  },

  OPTEX: {
    alambrico: ["RX-40QZ","CX-702","VX-402"],
    inalambrico: []
  }
};

// PIRS
const modelosPorMarcaRondin = {
  PROXIGUARD: {
    alambrico: ["PG-2002-S","PG-3000"]
  }
};


// Contactos magnéticos (CM)
const modelosPorMarcaCM = {
  HONEYWELL: {
    alambrico: ["7939WG","943WG","958"],
    inalambrico: ["5816","5800MINI","5811"]
  },

  DSC: {
    alambrico: ["DC-1025","DC-1025T","DC-104"],
    inalambrico: ["WS4945","PG9309","PG9945"]
  },

  SFIRE: {
    alambrico: ["SF-MAG01"],
    inalambrico: ["2023"]
  },

  SECOLARM: {
    alambrico: ["SM-226LQ","SM-4601LQ"],
    inalambrico: ["216Q/GY","226LQ","4601LQ"]
  },

  TANEALARM: {
    alambrico: ["TA-MAG01"],
    inalambrico: ["GP23"]
  }
};

// Botón de pánico (BTN)
const modelosPorMarcaBTN = {
  DMP: {
    alambrico: ["1142-W","1135"],
    inalambrico: ["1144-2","1148-G","1100PB"]
  },

  INOVONICS: {
    alambrico: ["EN1235-S","EN1236-D"],
    inalambrico: ["EN1235-SF","EN1223D"]
  },

  ACCESPRO: {
    alambrico: ["APBSEMC","PRO800B","ACCESSK1"],
    inalambrico: []
  },

  AXCEZE: {
    alambrico: ["AXB70R"],
    inalambrico: []
  },

  ENFORCER: {
    alambrico: ["SD-927-PKCNSQ","SD-7201"],
    inalambrico: []
  }
};


// Overhead (OH)
const modelosPorMarcaOH = {
  HONEYWELL: { alambrico: ["7939WG","943WG"], inalambrico: ["5816","5800MINI"] },
  DSC:       { alambrico: ["DC-1025","DC-1025T"], inalambrico: ["WS4945","PG9309"] },
  SFIRE:     { alambrico: [], inalambrico: ["2023"] },
  SECOLARM:  { alambrico: [], inalambrico: ["216Q/GY","226LQ","4601LQ"] },
  TANEALARM: { alambrico: [], inalambrico: ["GP23"] }
};

// Estrobos
const modelosPorMarcaEstrobo = {
  SYSTEM_SENSOR: {
    alambrico: ["SPSRK","SPSWK","P2R"],
    inalambrico: []
  },

  GENTEX: {
    alambrico: ["GXS","GX90","SHG"],
    inalambrico: []
  },

  WHEELOCK: {
    alambrico: ["RSSP","MTWP"],
    inalambrico: []
  }
};


// Repetidoras (REP)
const modelosPorMarcaREP = {
  DMP: {
    alambrico: [],
    inalambrico: ["1100R-W","1121"]
  },

  INOVONICS: {
    alambrico: ["EN5040T","EN5040"],
    inalambrico: []
  }
};


// Ruptura de cristal (DRC)
const modelosPorMarcaDRC = {
  DMP: {
    alambrico: ["1128-W","EN1247"],
    inalambrico: ["1100GB"]
  },

  INOVONICS: {
    alambrico: ["EN1247"],
    inalambrico: []
  },

  DSC: {
    alambrico: ["DG50AU","AC-101"],
    inalambrico: []
  },

  HONEYWELL: {
    alambrico: ["FG1625T","FG701"],
    inalambrico: []
  }
};


// Monitores
const modelosPorMarcaMonitor = {
  DELL: {
    monitores: ["E2223HN","E2225HS","P2422H","U2723QE"]
  },

  SAMSUNG: {
    monitores: [
      "LS19A330NHLXZX","LS22C310EALXZX","LS24A310NHLXZX",
      "LS24A336NHLXZX","LS24A600NWLXZX",
      "LS49CG950SLXZX","S33A"
    ]
  },

  BENQ: {
    monitores: ["GW2283","GW2480","GW2490","GW2790"]
  },

  HANWHA: {
    monitores: ["SMT-1935","SMT-2233","SMT-3231"]
  },

  HP: {
    monitores: ["P22 G5","P24V G5","M22F","E24 G5"]
  },

  ZKTECO: {
    monitores: ["ZD43-4K"]
  },

  UNIVIEW: {
    monitores: ["MW3232-V-K","MW3232-E","MW3232-V-K2","MW3222-V-DT"]
  },

  LG: {
    monitores: ["32MR50C-B","32SR50F-W.AWM","27EP950-B"]
  },

  STARTECH: {
    monitores: ["FPWARTB1M","ARMUNONB"]
  }
};
// Joysticks (control CCTV inalámbrico)
const modelosPorMarcaJoystick = {
  AXIS: {
    inalambrico: ["T8311","T8312","T8313","T8314","T8316"]
  },

  HANWHA: {
    inalambrico: ["SPC-2000","SPC-2010","SPC-6000","SPC-6010"]
  },

  HIKVISION: {
    inalambrico: ["DS-1005KI","DS-1006KI","DS-1100KI","DS-1200KI","DS-1600KI"]
  },

  DAHUA: {
    inalambrico: ["NKB1000","NKB3000","DHI-NKB3000","DHI-NKB5000"]
  },

  UNIVIEW: {
    inalambrico: ["KB-1100","KB-3100","KB-1122"]
  },

  PELCO: {
    inalambrico: ["KBD300A","CM6800-KBD"]
  },

  BOSCH: {
    inalambrico: ["KBD-UNIVERSAL","IntuiKey"]
  }
};
// Estación manual
const modelosPorMarcaEstacionManual = {
  DMP: {
    alambrico: ["850S","864"],
    inalambrico: []
  },

  SYSTEM_SENSOR: {
    alambrico: ["BG-12","BG-12LX"],
    inalambrico: []
  }
};


// Estación de trabajo
const modelosPorMarcaEstacionTrabajo = {
  DELL: {
    estacion: [
      "OptiPlex 3080","OptiPlex 5090","OptiPlex 7080",
      "Precision 3460","Precision 3660"
    ]
  },

  HP: {
    estacion: [
      "ProDesk 600 G6","ProDesk 600 G9",
      "EliteDesk 800 G6","EliteDesk 800 G9",
      "Z2 G9","Z4 G5"
    ]
  },

  LENOVO: {
    estacion: [
      "ThinkCentre M70q","ThinkCentre M80q",
      "ThinkStation P330","ThinkStation P360"
    ]
  }
};


// ------------------------------------------------------------
// Helpers de normalización y búsqueda
// ------------------------------------------------------------

// ------------------------------------------------------------
// 🔹 Filtrar marcas por sub-tipo (ip, analogica, poe, plano, alambrico, inalambrico)
// ------------------------------------------------------------
function marcasDisponiblesPorTipo(dict, subkey) {
  return Object.entries(dict)
    .filter(([_, grupos]) => Array.isArray(grupos?.[subkey]) && grupos[subkey].length > 0)
    .map(([marca]) => marca)
    .sort();
}

const norm  = s => (s ?? '').toString().trim();
const normU = s => norm(s).toUpperCase();
const quitarAcentos = s => norm(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'');

function marcaKey(dict, valorSeleccion) {
  const val = normU(valorSeleccion);
  return Object.keys(dict).find(k => normU(k) === val) || null;
}
function flattenBySubkey(dict, subkey) {
  const out = [];
  Object.values(dict).forEach(grupo => {
    if (grupo && Array.isArray(grupo[subkey])) out.push(...grupo[subkey].map(normU));
  });
  return out;
}

// Switches: fallback global (como Set para rapidez)
const modelosPorTipo = {
  switch_poe:   new Set(flattenBySubkey(modelosPorMarca, 'poe')),
  switch_plano: new Set(flattenBySubkey(modelosPorMarca, 'plano'))
};

// --- Combinar todos los diccionarios de alarma en uno "ALARMA" ---
function mergeAlarmDicts() {
  const alarmDicts = [
    modelosPorMarcaDH, modelosPorMarcaPIR, modelosPorMarcaCM,
    modelosPorMarcaBTN, modelosPorMarcaOH, modelosPorMarcaEstrobo,
    modelosPorMarcaREP, modelosPorMarcaDRC, modelosPorMarcaEstacionManual, modelosPorMarcaRondin
  ];
  const merged = {}; // { MARCA: { alambrico:[], inalambrico:[] } }

  const pushArr = (to, arr) => {
    if (!Array.isArray(arr)) return;
    arr.forEach(m => { if (!to.includes(m)) to.push(m); });
  };

  alarmDicts.forEach(dict => {
    Object.entries(dict).forEach(([marca, grupos]) => {
      if (!merged[marca]) merged[marca] = { alambrico: [], inalambrico: [] };
      const keys = Object.keys(grupos || {});
      if (keys.includes('alambrico') || keys.includes('inalambrico')) {
        pushArr(merged[marca].alambrico, grupos.alambrico || []);
        pushArr(merged[marca].inalambrico, grupos.inalambrico || []);
      } else {
        Object.values(grupos || {}).forEach(v => pushArr(merged[marca].alambrico, v));
      }
    });
  });

  return merged;
}
const ALARMA_MERGED = mergeAlarmDicts();

// ------------------------------------------------------------
// Cache de elementos
// ------------------------------------------------------------
const equipoInput = document.getElementById('equipo');
const marcaSelect = document.getElementById('marca');
const modeloInput = document.getElementById('modelo');
const datalist    = document.getElementById('sugerencias-modelo');

const grupoCCTV   = document.querySelector('.grupo-cctv');
const grupoAlarma = document.querySelector('.grupo-alarma');
const campoRC     = document.querySelector('.campo-rc');
const ubicacionRC = document.querySelector('.campo-ubicacion-rc');
const campoWin    = document.querySelector('.campo-win');
const campoVmsVer = document.querySelector('.campo-vms-version');

const tipoAlarmaContainer = document.getElementById('tipoAlarmaContainer'); // "Alámbrico / Inalámbrico"
const tipoSwitchContainer = document.getElementById('tipoSwitchContainer'); // "PoE / Plano"
const tipoCamaraContainer = document.getElementById('tipoCamaraContainer'); // "IP / Analógica"

const inputTipoAlarma = document.getElementById('tipo_alarma');  // hidden
const inputTipoSwitch = document.getElementById('tipo_switch');  // hidden
const inputTipoCCTV   = document.getElementById('tipo_cctv');    // hidden

// --- Elementos para Marca manual ---
const marcaManualInput     = document.getElementById('marcaManual');
const toggleMarcaManualBtn = document.getElementById('toggleMarcaManual');

// Helpers UI
const show = (el, on) => el?.classList.toggle('d-none', !on);
const setReq = (input, on) => { if (input) input.required = !!on; };

// ------------------------------------------------------------
// Ocultado robusto por alias (name/id/label)
// ------------------------------------------------------------
function normTxt(t){return (t??'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().toUpperCase();}

const FIELD_ALIASES = {
  user:   { names:['user'],      ids:[],           labels:['IDE','USUARIO','USER'] },
  pass:   { names:['pass'],      ids:[],           labels:['IDE PASSWORD','CONTRASEÑA','PASSWORD'] },
  switch: { names:['switch'],    ids:[],           labels:['SWITCH'] },
  puerto: { names:['puerto'],    ids:[],           labels:['NO. PUERTO','PUERTO','PORT'] },
  mac:    { names:['mac'],       ids:['macInput'], labels:['DIRECCION MAC','MAC','MAC ADDRESS'] },
  ip:     { names:['ip','ipTag'],ids:['ipInput'],  labels:['DIRECCION IP','IP','IP ADDRESS'] }, // tu IP se llama ipTag
};

function uniqEls(arr){
  const s = new Set(); const out=[];
  arr.forEach(el=>{ if(el && !s.has(el)){ s.add(el); out.push(el);} });
  return out;
}
function findWrapper(el){
  return el?.closest('.col-md-3, .col-md-4, .col-md-6, .col-sm-6, .col-12, .form-group') || el?.parentElement || null;
}
function wrappersForAlias(key){
  const conf = FIELD_ALIASES[key] || {};
  let found = [];

  // por name
  (conf.names||[]).forEach(n=>{
    document.querySelectorAll(`[name="${n}"]`).forEach(el=>found.push(findWrapper(el)));
  });
  // por id directo
  (conf.ids||[]).forEach(id=>{
    const el = document.getElementById(id);
    if (el) found.push(findWrapper(el));
  });
  // por label visible
  const wanted = new Set((conf.labels||[]).map(normTxt));
  document.querySelectorAll('label').forEach(lbl=>{
    if (wanted.has(normTxt(lbl.textContent))) found.push(findWrapper(lbl));
  });

  return uniqEls(found.filter(Boolean));
}
function hideAliases(keys){
  keys.forEach(k=>{
    wrappersForAlias(k).forEach(w=>{
      w.classList.add('d-none'); w.style.display='none'; w.setAttribute('aria-hidden','true');
      w.querySelectorAll('input,select,textarea').forEach(i=>i.required=false);
    });
  });
  // Oculta también los “status” de MAC/IP si están fuera del wrapper
  if (keys.includes('mac')){
    const tag = document.getElementById('tag');
    const wt = tag ? findWrapper(tag) : null;
    if (wt){ wt.classList.add('d-none'); wt.style.display='none'; wt.setAttribute('aria-hidden','true'); }
  }
  if (keys.includes('ip')){
    const ip = document.getElementById('ip');
    const wi = ip ? findWrapper(ip) : null;
    if (wi){ wi.classList.add('d-none'); wi.style.display='none'; wi.setAttribute('aria-hidden','true'); }
  }
}
function showAliases(keys){
  keys.forEach(k=>{
    wrappersForAlias(k).forEach(w=>{
      w.classList.remove('d-none'); w.style.display=''; w.setAttribute('aria-hidden','false');
    });
  });
}
function setRequiredAliases(keys, on){
  keys.forEach(k=>{
    wrappersForAlias(k).forEach(w=>{
      w.querySelectorAll('input,select,textarea').forEach(i=>i.required=!!on);
    });
  });
}

// ------------------------------------------------------------
// 1) Gestión dropzones (sencilla)
// ------------------------------------------------------------
document.querySelectorAll('.dropzone').forEach(dropzone => {
  const inputId = dropzone.dataset.input;
  const fileInput = document.getElementById(inputId);
  const preview = document.getElementById(`preview-${inputId}`);
  const removeBtn = dropzone.querySelector('.remove-btn');
  const icono = dropzone.querySelector('.icono');
  const mensaje = dropzone.querySelector('.mensaje');

  let currentUrl = null;

  const setPreview = (file) => {
    if (preview) {
      if (currentUrl) { URL.revokeObjectURL(currentUrl); currentUrl = null; }
      currentUrl = URL.createObjectURL(file);
      preview.src = currentUrl;
      preview.classList.remove('d-none');
    }
  };

  const resetImage = () => {
    if (!fileInput) return;
    fileInput.value = '';
    if (currentUrl) { URL.revokeObjectURL(currentUrl); currentUrl = null; }
    if (preview) { preview.src = '#'; preview.classList.add('d-none'); }
    if (removeBtn) removeBtn.classList.add('d-none');
    if (icono) icono.classList.remove('d-none');
    if (mensaje) mensaje.classList.remove('d-none');
  };

  dropzone.addEventListener('click', () => fileInput?.click());

  fileInput?.addEventListener('change', () => {
    if (fileInput.files.length) {
      const file = fileInput.files[0];
      if (file && file.type.startsWith('image/')) {
        setPreview(file);
        removeBtn?.classList.remove('d-none');
        icono?.classList.add('d-none');
        mensaje?.classList.add('d-none');
      }
    }
  });

  dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('bg-light'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('bg-light'));
  dropzone.addEventListener('drop', e => {
    e.preventDefault(); dropzone.classList.remove('bg-light');
    const file = e.dataTransfer.files?.[0];
    if (file && file.type.startsWith('image/')) {
      const dt = new DataTransfer(); dt.items.add(file);
      if (fileInput) fileInput.files = dt.files;
      setPreview(file);
      removeBtn?.classList.remove('d-none');
      icono?.classList.add('d-none');
      mensaje?.classList.add('d-none');
    }
  });

  removeBtn?.addEventListener('click', e => { e.stopPropagation(); resetImage(); });
});

// ------------------------------------------------------------
// 2) Detección de categoría y visibilidad de bloques
// ------------------------------------------------------------
const CAT_MAP = [
  { cat:'switch', keys:[/^\s*switch\s*$/i,'SWITCH'] },
  { cat:'camara', keys:[/^\s*(camara|cctv)\s*$/i,'CAMARA','CCTV'] },
  { cat:'nvr',    keys:['NVR'] },
  { cat:'dvr',    keys:['DVR'] },
  { cat:'ups',    keys:['UPS'] },
  { cat:'servidor', keys:['SERVIDOR','SERVER'] },
  { cat:'monitor', keys:['MONITOR','DISPLAY'] },
  { cat:'estacion_trabajo', keys:['ESTACION TRABAJO','ESTACION DE TRABAJO','WORKSTATION','PC','COMPUTADORA'] },
  { cat:'joystick', keys:[/JOYSTICK/i, /PTZ CONTROLLER/i, /CONTROL PTZ/i] }
];

// Palabras que disparan la categoría "alarma" (incluye tus nuevos términos)
const ALARMA_KEYS = [
  "ALARMA","TRANSMISOR","SENSOR","DETECTOR","HUMO","OVER HEAD","OVERHEAD","ZONA",
  "BOTON","BOTON PANICO","PANICO","ESTACION","PULL STATION","PULL",
  "PANEL","CABLEADO","SIRENA","RECEPTOR","EMISOR","LLAVIN","TECLADO", "BATERIA", "BATERIAS",
  "ESTROBO","CRISTAL","RUPTURA","REPETIDOR","REPETIDORA","DH","PIR","CM","BTN","OH","DRC","REP",
  "ROTARI","RATONERA","EXPANSORA","TRANSFORMADOR","MODULO","MODULOS", "RONDIN", "RONDINERO"
].map(s => quitarAcentos(s.toUpperCase()));

function detectarCategoria(texto) {
  const raw = String(texto || '');
  const v = quitarAcentos(normU(raw))
              .replace(/[_-]+/g, ' ')
              .replace(/\s+/g, ' ')
              .trim();

  if (v.includes('JOYSTICK')) return 'joystick';
  if (ALARMA_KEYS.some(k => v.includes(k))) {
    return 'alarma';
  }

  // 2) Casos especiales explícitos
  if (/(^|[\s\-])(rotari|ratonera|expansora|transformador|modulo|modulos)([\s\-]|$)/i.test(raw)) {
    return 'alarma';
  }

  // 3) Luego sí, CCTV / otros
  for (const {cat, keys} of CAT_MAP) {
    if (keys.some(k =>
      typeof k === 'string'
        ? v.includes(quitarAcentos(k))
        : k.test(v)
    )) {
      return cat;
    }
  }

  return 'otro';
}


// === MOSTRAR/OCULTAR por categoría (reglas)
function toggleGruposPorCategoria(cat) {
  const esCamaraLike = new Set(['camara','servidor','nvr','dvr']).has(cat);
  const esAlarmaLike = new Set(['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual, fuente, baterias, rondinero, rondin']).has(cat);
  const esMonitor    = (cat === 'monitor');
  const esSwitch     = (cat === 'switch');
  const esJoystick   = (cat === 'joystick'); // ← AGREGAR ESTA LÍNEA

  // Bloques especiales
  show(grupoCCTV,   esCamaraLike);
  show(grupoAlarma, esAlarmaLike);

  // RC visible salvo 'otro' y salvo alarma
  const mostrarRC = (cat !== 'otro' && !esAlarmaLike && !esJoystick); // ← MODIFICAR ESTA LÍNEA
  show(campoRC,  mostrarRC);

  // Campos específicos de servidor
  show(ubicacionRC, cat === 'servidor');
  show(campoWin,    cat === 'servidor');
  show(campoVmsVer, cat === 'servidor');

  // Controles de tipo
  show(tipoSwitchContainer, esSwitch);
  if (tipoCamaraContainer) tipoCamaraContainer.style.display = (cat === 'camara') ? 'block' : 'none';
  show(tipoAlarmaContainer, esAlarmaLike);

  // RESET: mostrar todo lo común y limpiar required
  showAliases(['user','pass','switch','puerto','mac','ip']);
  setRequiredAliases(['user','pass','switch','puerto','mac','ip'], false);

  // ALARMA: ocultar MAC/IP y credenciales
  if (esAlarmaLike) {
    hideAliases(['mac','ip','user','pass','switch','puerto','rc','ubicacion_rc']);
    setRequiredAliases(['user','pass','switch','puerto','rc','ubicacion_rc'], false);
  }

  // MONITOR: ocultar user/pass, switch, puerto, mac, ip
  if (esMonitor) {
    hideAliases(['user','pass','switch','puerto','mac','ip']);
  }
    // ➕ JOYSTICK: ocultar TODOS los campos técnicos (como ALARMA pero SIN credenciales)
  if (esJoystick) {
    setRequiredAliases(['user','pass','switch','puerto','mac','ip','rc'], false);
    hideAliases(['user','pass','switch','puerto','mac','ip','rc']);
  }

  // SWITCH: ocultar MAC/IP
  if (esSwitch) {
    hideAliases(['mac','ip']);
  }

  // ➕ UPS: ocultar IP, IDE y IDE Password
  if (cat === 'ups') {

  // Quitar required ANTES de ocultar
  setRequiredAliases(['ip','user','pass'], false);
  // Ahora sí ocultamos
  hideAliases(['ip','user','pass']);
    // Ocultar los estados de validación también
  document.getElementById('tag')?.closest('.form-group')?.classList.add('d-none');  
  document.getElementById('ip')?.closest('.form-group')?.classList.add('d-none');
  document.getElementById('user')?.closest('.form-group')?.classList.add('d-none');
}


  // Credenciales required solo cuando NO es monitor NI alarma
let credVisibles = (!esMonitor && !esAlarmaLike && !esJoystick);

// 🚨 UPS no usa credenciales
if (cat === 'ups') credVisibles = false;

setRequiredAliases(['user','pass'], credVisibles);


  // Sincroniza wrappers específicos si existen
  const userWrap = document.querySelector('.campo-user');
  const passWrap = document.querySelector('.campo-pass');
  if (userWrap) userWrap.classList.toggle('d-none', !credVisibles);
  if (passWrap) passWrap.classList.toggle('d-none', !credVisibles);
}

// ------------------------------------------------------------
// 3) Marcas y modelos según categoría
// ------------------------------------------------------------
const diccionarioPorCategoria = {
  switch:           modelosPorMarca,
  camara:           modelosPorMarcaCamara,
  nvr:              modelosPorMarcaNVR,
  dvr:              modelosPorMarcaDVR,
  ups:              modelosPorMarcaUPS,
  servidor:         modelosPorMarcaServidor,
  dh:               modelosPorMarcaDH,
  pir:              modelosPorMarcaPIR,
  cm:               modelosPorMarcaCM,
  btn:              modelosPorMarcaBTN,
  oh:               modelosPorMarcaOH,
  estrobo:          modelosPorMarcaEstrobo,
  rep:              modelosPorMarcaREP,
  drc:              modelosPorMarcaDRC,
  monitor:          modelosPorMarcaMonitor,
  estacionmanual:   modelosPorMarcaEstacionManual,
  estacion_trabajo: modelosPorMarcaEstacionTrabajo,
  alarma:           ALARMA_MERGED,
  joystick:         modelosPorMarcaJoystick,
  rondin:           modelosPorMarcaRondin
};

function llenarMarcasPorCategoria(cat, subTipo = null) {
  const dict = diccionarioPorCategoria[cat];
  if (!marcaSelect || !dict) return;

  marcaSelect.innerHTML = '<option value="">-- Selecciona una marca --</option>';

  let marcas = [];

  // 🔥 FILTRO POR SUBTIPO
  if (subTipo) {
    marcas = marcasDisponiblesPorTipo(dict, subTipo);
  } else {
    marcas = Object.keys(dict).sort();
  }

  marcas.forEach(marca => {
    const opt = document.createElement('option');
    opt.value = marca;
    opt.textContent = marca;
    marcaSelect.appendChild(opt);
  });
}


function limpiarDatalist() { if (datalist) datalist.innerHTML = ''; }

function setDatalistModelos(modelos, {autocompletarUnico = true} = {}) {
  if (!datalist) return;
  limpiarDatalist();
  const frag = document.createDocumentFragment();
  modelos.forEach(modelo => {
    const option = document.createElement('option');
    option.value = String(modelo);
    frag.appendChild(option);
  });
  datalist.appendChild(frag);

  if (modeloInput) {
    if (autocompletarUnico && modelos.length === 1) {
      modeloInput.value = modelos[0];
    } else if (modeloInput.value && !modelos.map(normU).includes(normU(modeloInput.value))) {
      modeloInput.value = '';
    }
  }
}

function llenarModelos(cat, mk) {
  limpiarDatalist();
  const dict = diccionarioPorCategoria[cat] || null;
  if (!dict) return;

  const key = marcaKey(dict, mk);
  if (!key) return;

  const modelos = Object.values(dict[key]).flat().map(String);
  setDatalistModelos(modelos, { autocompletarUnico: true });
}

// ------------------------------------------------------------
// 4) Filtrado por conexión + resaltado y clasificación
// ------------------------------------------------------------
function normalizaConexionLabel(label) {
  const v = quitarAcentos(String(label || '')).toLowerCase();
  if (v.includes('inalambr')) return 'inalambrico';
  if (v.includes('alambr'))   return 'alambrico';
  return null;
}

// Marca manual: helpers
function isMarcaManualMode() {
  return marcaManualInput && !marcaManualInput.classList.contains('d-none');
}
function getMarcaActual() {
  const manual = norm(marcaManualInput?.value);
  if (isMarcaManualMode() && manual) return manual;
  return marcaSelect?.value || '';
}
function setMarcaValueForSubmit(value) {
  if (!marcaSelect || !value) return;
  const val = norm(value);
  let opt = [...marcaSelect.options].find(o => normU(o.value) === normU(val));
  if (!opt) {
    opt = new Option(val, val, true, true);
    marcaSelect.add(opt);
  }
  marcaSelect.value = opt.value;
}

// Devuelve modelos para {categoria, marca, conexion}
function obtenerModelosPorConexion(cat, mk, conexion /* 'alambrico'|'inalambrico' */) {
  const dict = diccionarioPorCategoria[cat];
  if (!dict) return [];

  const key = mk ? marcaKey(dict, mk) : null;

  const pushAll = (out, modelos) => {
    if (Array.isArray(modelos)) modelos.forEach(m => out.push(String(m)));
  };

  if (key) {
    const grupo = dict[key] || {};
    if (Array.isArray(grupo[conexion])) {
      return grupo[conexion].map(m => String(m));
    }
    const out = [];
    Object.values(grupo).forEach(v => pushAll(out, v));
    return [...new Set(out)];
  }

  const out = [];
  Object.values(dict).forEach(grupo => {
    if (Array.isArray(grupo?.[conexion])) {
      pushAll(out, grupo[conexion]);
    } else {
      Object.values(grupo || {}).forEach(v => pushAll(out, v));
    }
  });
  return [...new Set(out)];
}

function activarBotones(selector, textoObjetivo) {
  const botones = document.querySelectorAll(selector);

  botones.forEach(btn => btn.classList.remove('activo'));

  if (!textoObjetivo) return;

  botones.forEach(btn => {
    if (btn.textContent.trim().toLowerCase() === textoObjetivo.trim().toLowerCase()) {
      btn.classList.add('activo');
    }
  });
}


function clasificarAlarmaPorModelo(cat) {
  // 🚫 INTENCIONALMENTE DESACTIVADO
  // En dispositivos de ALARMA:
  // - El tipo (Alámbrico / Inalámbrico)
  // - SOLO lo elige el usuario
  // - NUNCA el modelo
  return;
}


function clasificarPorModelo(cat) {
  const modelo = normU(modeloInput?.value || '');
  const mk = getMarcaActual();
  if (!modelo) return;

  if (cat === 'switch') {
    let esPoe = modelosPorTipo.switch_poe.has(modelo);
    let esPlano = modelosPorTipo.switch_plano.has(modelo);

    const key = marcaKey(modelosPorMarca, mk);
    if (key) {
      const poe   = new Set((modelosPorMarca[key].poe   || []).map(normU));
      const plano = new Set((modelosPorMarca[key].plano || []).map(normU));
      esPoe = poe.has(modelo) || esPoe;
      esPlano = plano.has(modelo) || esPlano;
    }

    if (esPoe)  { if (inputTipoSwitch) inputTipoSwitch.value = 'PoE';   activarBotones('.tipo-switch','PoE'); }
    else if (esPlano) { if (inputTipoSwitch) inputTipoSwitch.value = 'Plano'; activarBotones('.tipo-switch','Plano'); }
    else { if (inputTipoSwitch) inputTipoSwitch.value = ''; }
    return;
  }

  if (cat === 'camara') {
    const key = marcaKey(modelosPorMarcaCamara, mk);
    if (key) {
      const ipSet = new Set((modelosPorMarcaCamara[key].ip || []).map(normU));
      const anSet = new Set((modelosPorMarcaCamara[key].analogica || []).map(normU));
      if (ipSet.has(modelo)) {
        if (inputTipoCCTV) inputTipoCCTV.value = 'IP';
        activarBotones('#tipoCamaraContainer .btn', 'IP');
      } else if (anSet.has(modelo)) {
        if (inputTipoCCTV) inputTipoCCTV.value = 'Analógica';
        activarBotones('#tipoCamaraContainer .btn', 'Analógica');
      }
    }
    return;
  }

  if (new Set(['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual, rondin']).has(cat)) {
    clasificarAlarmaPorModelo(cat);
  }
}

// ------------------------------------------------------------
// 5) Validaciones (MAC / IP)
// ------------------------------------------------------------
function setStatus(elId, ok, okMsg, badMsg) {
  const el = document.getElementById(elId);
  if (!el) return;
  const msg = ok ? okMsg : badMsg;
  if ('value' in el) el.value = msg; else el.textContent = msg;
  el.style.color = ok ? 'green' : 'red';
}

function formatearYValidarMac(input) {
  let valor = input.value.replace(/[^A-Fa-f0-9]/g,'').toUpperCase().slice(0, 12);
  input.value = valor.match(/.{1,2}/g)?.join(':') ?? '';
  const ok = /^([0-9A-F]{2}:){5}[0-9A-F]{2}$/.test(input.value);
  setStatus('tag', ok, '✅ MAC válida', '❌ MAC inválida');
}

function validarIP(input) {
  const ip = input.value.replace(/[^0-9.]/g, '');
  input.value = ip;
  const partes = ip.split('.');
  const ok = partes.length === 4 && partes.every(p => {
    if (!/^\d{1,3}$/.test(p)) return false;
    const n = +p; return n >= 0 && n <= 255;
  });
  setStatus('ip', ok, '✅ IP válida', '❌ IP inválida');
}

// ------------------------------------------------------------
// 6) Municipios dinámicos con AbortController (opcional)
// ------------------------------------------------------------
let muniAbortCtrl = null;

document.getElementById('ciudad')?.addEventListener('change', function () {
  const ciudadID = this.value;
  const municipioSelect = document.getElementById('municipio');
  if (!municipioSelect) return;
  municipioSelect.innerHTML = '<option value="">Cargando municipios...</option>';

  try { muniAbortCtrl?.abort(); } catch {}
  muniAbortCtrl = new AbortController();

  fetch(`obtener_municipios.php?ciudad_id=${encodeURIComponent(ciudadID)}`, { signal: muniAbortCtrl.signal })
    .then(r => r.ok ? r.json() : [])
    .then(data => {
      municipioSelect.innerHTML = '<option value="">-- Selecciona un municipio --</option>';
      (data || []).forEach(m => {
        const o = document.createElement('option');
        o.value = m.ID ?? m.id;
        o.textContent = m.nom_municipio ?? m.nombre ?? '';
        if (o.value && o.textContent) municipioSelect.appendChild(o);
      });
    })
    .catch(err => {
      if (err?.name === 'AbortError') return;
      municipioSelect.innerHTML = '<option value="">Error al cargar</option>';
    });
});

// ------------------------------------------------------------
// 7) Wiring de eventos
// ------------------------------------------------------------
function actualizarModelosSegunConexion() {
  const cat = detectarCategoria(equipoInput?.value || '');
  const mk = getMarcaActual();

  if (cat === 'joystick') {
    const dict = modelosPorMarcaJoystick;
    const key = mk ? marcaKey(dict, mk) : null;
const modelos = key ? dict[key].inalambrico.map(m => String(m)) : [];
    setDatalistModelos(modelos);
    llenarMarcasPorCategoria('joystick');
    return;
  }

  const conexion = normalizaConexionLabel(inputTipoAlarma?.value || '');
  if (!conexion) return;
  const modelos = obtenerModelosPorConexion(cat, mk, conexion);
  setDatalistModelos(modelos);
}


function onEquipoChange() {
  const cat = detectarCategoria(equipoInput?.value || '');
    
if (cat === 'joystick') {
  llenarMarcasPorCategoria('joystick','inalambrico');
    const mk = getMarcaActual();
    const modelos = obtenerModelosPorConexion('joystick', mk, 'inalambrico');
    setDatalistModelos(modelos);
  }

  // 1️⃣ Mostrar / ocultar bloques
  toggleGruposPorCategoria(cat);

  // 2️⃣ Llenar marcas según categoría
  llenarMarcasPorCategoria(cat);

  // 3️⃣ Limpiar modelos
  limpiarDatalist();
  if (modeloInput) modeloInput.value = '';
  document.querySelectorAll('#tipoAlarmaContainer .tipo-alarma')
  .forEach(btn => btn.classList.remove('activo'));

  // 4️⃣ Reset de botones de otros tipos
  if (cat !== 'switch' && inputTipoSwitch) {
    inputTipoSwitch.value = '';
    activarBotones('.tipo-switch','');
  }

  if (cat !== 'camara' && inputTipoCCTV) {
    inputTipoCCTV.value = '';
    activarBotones('#tipoCamaraContainer .btn','');
  }

  // 🚨 5️⃣ ALARMA: NO auto-seleccionar Alámbrico / Inalámbrico
  const esAlarmaLike = new Set([
    'alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual, rondin'
  ]).has(cat);

  if (!esAlarmaLike && inputTipoAlarma) {
    inputTipoAlarma.value = '';
    activarBotones('#tipoAlarmaContainer .btn','');
  }
// 🔴 RESET VISUAL FORZADO DE BOTONES DE ALARMA
document.querySelectorAll('#tipoAlarmaContainer .tipo-alarma')
  .forEach(btn => btn.classList.remove('activo'));

}


function onMarcaChange() {
  const cat = detectarCategoria(equipoInput?.value || '');
if (cat === 'joystick') {
  llenarModelos('joystick', getMarcaActual());
  return;
}
  if (modeloInput) modeloInput.value = '';

  const esAlarmaLike = new Set(['alarma','dh','pir','cm','btn','oh','estrobo','rep','drc','estacionmanual, rondin']).has(cat);
  if (esAlarmaLike && normalizaConexionLabel(inputTipoAlarma?.value || '')) {
    actualizarModelosSegunConexion();
  } else {
    llenarModelos(cat, getMarcaActual());
  }

  clasificarPorModelo(cat);
}

function onModeloInput() {
  const cat = detectarCategoria(equipoInput?.value || '');
  clasificarPorModelo(cat);
}

// Expuesto para oninput del HTML si lo usas
window.actualizarMarcaYBotones = function () { onEquipoChange(); };

document.addEventListener('DOMContentLoaded', () => {

// Botones Alámbrico / Inalámbrico (FILTRA MARCAS)
document.querySelectorAll('.tipo-alarma').forEach(btn => {
  btn.addEventListener('click', () => {

    // 🛑 SI YA ESTÁ ACTIVO, NO HACER NADA
    if (btn.classList.contains('activo')) return;

    // 🔒 Exclusividad manual
    document.querySelectorAll('.tipo-alarma')
      .forEach(b => b.classList.remove('activo'));

    btn.classList.add('activo');

    const subTipo = normalizaConexionLabel(btn.textContent);

    if (inputTipoAlarma) {
      inputTipoAlarma.value = subTipo === 'inalambrico'
        ? 'Inalámbrico'
        : 'Alámbrico';
    }

    llenarMarcasPorCategoria('alarma', subTipo);
    limpiarDatalist();
    modeloInput.value = '';
  });
});

  // Botones PoE / Plano
document.querySelectorAll('.tipo-switch').forEach(btn => {
  btn.addEventListener('click', () => {
    activarBotones('.tipo-switch', btn.textContent);

    const subTipo = /poe/i.test(btn.textContent) ? 'poe' : 'plano';

    if (inputTipoSwitch) {
      inputTipoSwitch.value = subTipo === 'poe' ? 'PoE' : 'Plano';
    }

    // 🔥 FILTRAR MARCAS DE SWITCH
    llenarMarcasPorCategoria('switch', subTipo);
    limpiarDatalist();
    modeloInput.value = '';
  });
});

// Botones IP / Analógica (FILTRA MARCAS)
document.querySelectorAll('#tipoCamaraContainer .btn').forEach(btn => {
  btn.addEventListener('click', () => {
    activarBotones('#tipoCamaraContainer .btn', btn.textContent);

    const subTipo = /ip$/i.test(btn.textContent) ? 'ip' : 'analogica';

    if (inputTipoCCTV) {
      inputTipoCCTV.value = subTipo === 'ip' ? 'IP' : 'Analógica';
    }

    // 🔥 FILTRAR MARCAS POR TIPO DE CÁMARA
    llenarMarcasPorCategoria('camara', subTipo);
    limpiarDatalist();
    modeloInput.value = '';
  });
});


  // Marca manual: toggle + sincronización
  function showManual() {
    if (!marcaManualInput || !marcaSelect) return;
    marcaManualInput.classList.remove('d-none');
    marcaSelect.disabled = true;
    setTimeout(() => marcaManualInput.focus(), 0);
  }
  function hideManual() {
    if (!marcaManualInput || !marcaSelect) return;
    marcaManualInput.classList.add('d-none');
    marcaSelect.disabled = false;
  }
  function syncSelectFromManual() {
    const val = (marcaManualInput?.value || '').trim();
    if (!val) return;
    setMarcaValueForSubmit(val);
    marcaSelect?.dispatchEvent(new Event('change', { bubbles: true }));
  }

  toggleMarcaManualBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    if (!marcaManualInput) return;
    if (marcaManualInput.classList.contains('d-none')) { showManual(); } else { hideManual(); }
  });
  marcaManualInput?.addEventListener('input',   syncSelectFromManual);
  marcaManualInput?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); syncSelectFromManual(); }});
  marcaManualInput?.addEventListener('blur',    syncSelectFromManual);

  // Eventos principales
  equipoInput?.addEventListener('input', onEquipoChange);
  equipoInput?.addEventListener('change', onEquipoChange); // asegura disparo con datalist
  marcaSelect?.addEventListener('change', onMarcaChange);
  modeloInput?.addEventListener('input', onModeloInput);

  // Primera evaluación al cargar
  onEquipoChange();
    // 🛡️ DEFENSA FINAL: nunca guardar credenciales en ALARMA
  const form = document.querySelector('form[action="guardar.php"]');
  if (form) {
    form.addEventListener('submit', () => {
      const cat = detectarCategoria(equipoInput?.value || '');
      if (cat === 'alarma'  || cat === 'joystick') {
        const user = document.querySelector('[name="user"]');
        const pass = document.querySelector('[name="pass"]');
        if (user) user.value = '';
        if (pass) pass.value = '';
      }
    });
  }
});
// ------------------------------------------------------------
// 8) Efecto desvanecer sugerencia
// ------------------------------------------------------------
setTimeout(() => {
  const sugerencia = document.getElementById('sugerencia');
  if (sugerencia) {
    sugerencia.style.opacity = '0';
    setTimeout(() => sugerencia.remove(), 1000);
  }
}, 3000);