const formatoMonto = new Intl.NumberFormat("es-DO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function escaparXml(valor) {
  return String(valor ?? "")
    .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

function nombreColumna(indice) {
  let nombre = "";
  for (let valor = indice + 1; valor > 0; valor = Math.floor((valor - 1) / 26)) {
    nombre = String.fromCharCode(65 + (valor - 1) % 26) + nombre;
  }
  return nombre;
}

function crc32(bytes) {
  let crc = 0xffffffff;
  for (const byte of bytes) {
    crc ^= byte;
    for (let indice = 0; indice < 8; indice++) {
      crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
    }
  }
  return (crc ^ 0xffffffff) >>> 0;
}

function archivoZip(archivos) {
  const codificar = new TextEncoder();
  const partes = [];
  const directorio = [];
  let posicion = 0;

  for (const [ruta, contenido] of Object.entries(archivos)) {
    const nombre = codificar.encode(ruta);
    const datos = codificar.encode(contenido);
    const suma = crc32(datos);
    const local = new Uint8Array(30 + nombre.length);
    const vistaLocal = new DataView(local.buffer);
    vistaLocal.setUint32(0, 0x04034b50, true);
    vistaLocal.setUint16(4, 20, true);
    vistaLocal.setUint32(14, suma, true);
    vistaLocal.setUint32(18, datos.length, true);
    vistaLocal.setUint32(22, datos.length, true);
    vistaLocal.setUint16(26, nombre.length, true);
    local.set(nombre, 30);
    partes.push(local, datos);

    const central = new Uint8Array(46 + nombre.length);
    const vistaCentral = new DataView(central.buffer);
    vistaCentral.setUint32(0, 0x02014b50, true);
    vistaCentral.setUint16(4, 20, true);
    vistaCentral.setUint16(6, 20, true);
    vistaCentral.setUint32(16, suma, true);
    vistaCentral.setUint32(20, datos.length, true);
    vistaCentral.setUint32(24, datos.length, true);
    vistaCentral.setUint16(28, nombre.length, true);
    vistaCentral.setUint32(42, posicion, true);
    central.set(nombre, 46);
    directorio.push(central);
    posicion += local.length + datos.length;
  }

  const inicioDirectorio = posicion;
  for (const entrada of directorio) {
    partes.push(entrada);
    posicion += entrada.length;
  }

  const fin = new Uint8Array(22);
  const vistaFin = new DataView(fin.buffer);
  vistaFin.setUint32(0, 0x06054b50, true);
  vistaFin.setUint16(8, directorio.length, true);
  vistaFin.setUint16(10, directorio.length, true);
  vistaFin.setUint32(12, posicion - inicioDirectorio, true);
  vistaFin.setUint32(16, inicioDirectorio, true);
  partes.push(fin);
  return new Blob(partes, { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
}

export function crearExcel(tabla) {
  const columnas = tabla.encabezados.length;
  const filas = [[tabla.titulo], [tabla.periodo], tabla.encabezados, ...tabla.filas, tabla.totales];
  const celdas = filas.map((fila, indiceFila) => {
    const contenido = fila.map((valor, indiceColumna) => {
      const referencia = `${nombreColumna(indiceColumna)}${indiceFila + 1}`;
      const numerico = indiceFila > 2 && typeof valor === "number" && Number.isFinite(valor);
      return numerico
        ? `<c r="${referencia}" s="1"><v>${valor}</v></c>`
        : `<c r="${referencia}" t="inlineStr"${indiceFila <= 2 || indiceFila === filas.length - 1 ? ' s="2"' : ""}><is><t>${escaparXml(valor)}</t></is></c>`;
    }).join("");
    return `<row r="${indiceFila + 1}">${contenido}</row>`;
  }).join("");
  const anchos = tabla.encabezados.map((encabezado, indice) => `<col min="${indice + 1}" max="${indice + 1}" width="${Math.min(38, Math.max(15, encabezado.length + 3))}" customWidth="1"/>`).join("");
  const ultimo = nombreColumna(columnas - 1);
  const hoja = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>${anchos}</cols><sheetData>${celdas}</sheetData><mergeCells count="2"><mergeCell ref="A1:${ultimo}1"/><mergeCell ref="A2:${ultimo}2"/></mergeCells></worksheet>`;
  const estilos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>';
  return archivoZip({
    "[Content_Types].xml": '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
    "_rels/.rels": '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
    "xl/workbook.xml": '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Ventas" sheetId="1" r:id="rId1"/></sheets></workbook>',
    "xl/_rels/workbook.xml.rels": '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
    "xl/worksheets/sheet1.xml": hoja,
    "xl/styles.xml": estilos,
  });
}

function descargar(blob, nombre) {
  const enlace = document.createElement("a");
  const url = URL.createObjectURL(blob);
  enlace.href = url;
  enlace.download = nombre;
  document.body.appendChild(enlace);
  enlace.click();
  enlace.remove();
  setTimeout(() => URL.revokeObjectURL(url), 30000);
}

export function descargarExcel(tabla, nombre) {
  descargar(crearExcel(tabla), `${nombre}.xlsx`);
}

export async function descargarPdf({ titulo, periodo, tablas }, nombre) {
  const [{ default: pdfMake }, { default: fuentes }] = await Promise.all([
    import("pdfmake/build/pdfmake.js"),
    import("pdfmake/build/vfs_fonts.js?raw"),
  ]);
  const contenidoFuentes = fuentes.match(/\.vfs\s*=\s*(\{[\s\S]*\});?\s*$/)?.[1];
  if (!contenidoFuentes) throw new Error("No se pudieron cargar las fuentes del PDF.");
  pdfMake.vfs = JSON.parse(contenidoFuentes);

  pdfMake.createPdf(definicionPdf({ titulo, periodo, tablas })).download(`${nombre}.pdf`);
}

export function definicionPdf({ titulo, periodo, tablas }) {
  const contenido = [{ text: titulo, style: "titulo" }, { text: periodo, style: "periodo" }];
  tablas.forEach((tabla) => {
    if (tabla.titulo) contenido.push({ text: tabla.titulo, style: "seccion" });
    const encabezar = tabla.encabezados.map((texto) => ({ text: texto, bold: true, fillColor: "#dce7f8" }));
    const grupos = tabla.gruposColumnas?.flatMap((grupo) => [
      { text: grupo.titulo, colSpan: grupo.columnas, rowSpan: grupo.fijo ? 2 : undefined, bold: true, fillColor: "#dce7f8", alignment: "center" },
      ...Array.from({ length: grupo.columnas - 1 }, () => ({})),
    ]);
    const encabezados = grupos
      ? [grupos, tabla.encabezados.map((texto, columna) => columna < tabla.columnasFijas ? {} : { text: texto, bold: true, fillColor: "#dce7f8" })]
      : [encabezar];
    const filaPdf = (fila, negrita = false) => fila.map((valor) => ({
      text: typeof valor === "number" ? formatoMonto.format(valor) : String(valor ?? ""),
      alignment: typeof valor === "number" ? "right" : "left",
      bold: negrita,
    }));
    contenido.push({
      table: { headerRows: encabezados.length, widths: Array(tabla.encabezados.length).fill("*"), body: [...encabezados, ...tabla.filas.map((fila) => filaPdf(fila)), filaPdf(tabla.totales, true)] },
      layout: { hLineColor: "#cad3df", vLineColor: "#cad3df", paddingLeft: () => 3, paddingRight: () => 3, paddingTop: () => 3, paddingBottom: () => 3 },
      fontSize: tabla.encabezados.length > 14 ? 5.5 : 7,
    });
  });
  const columnasMaximas = Math.max(...tablas.map((tabla) => tabla.encabezados.length));
  return {
    pageSize: columnasMaximas > 20 ? { width: Math.max(1684, columnasMaximas * 72), height: 1190 } : columnasMaximas > 14 ? "A2" : "A3",
    pageOrientation: "landscape",
    pageMargins: [24, 28, 24, 28],
    content: contenido,
    styles: { titulo: { fontSize: 15, bold: true, margin: [0, 0, 0, 4] }, periodo: { fontSize: 9, color: "#475569", margin: [0, 0, 0, 12] }, seccion: { fontSize: 11, bold: true, margin: [0, 0, 0, 8] } },
  };
}

export function tablaVentas(resultado, columnas, filas, titulo, periodo) {
  const encabezados = [
    ...columnas.map((columna) => columna.label),
    ...resultado.tipos.flatMap((tipo) => [tipo, `Promedio ${tipo}`]),
    "Venta total", "Promedio total",
  ];
  const valores = filas.map((fila) => [
    ...columnas.map((columna) => fila[columna.key] ?? ""),
    ...resultado.tipos.flatMap((tipo) => [Number(fila.tipos?.[tipo] ?? 0), Number(fila.promedios_por_tipo?.[tipo] ?? 0)]),
    Number(fila.total_venta ?? 0), Number(fila.promedio ?? 0),
  ]);
  const totales = [
    "Total", ...columnas.slice(1).map(() => ""),
    ...resultado.tipos.flatMap((tipo) => [Number(resultado.totales.por_tipo?.[tipo] ?? 0), Number(resultado.totales.promedios_por_tipo?.[tipo] ?? 0)]),
    Number(resultado.totales.total_venta ?? 0), Number(resultado.totales.promedio ?? 0),
  ];
  return { titulo, periodo, encabezados, filas: valores, totales };
}

export function tablasPeriodo(resultado, columnaPrincipal, filas, titulo) {
  const periodo = `${resultado.meses[0]?.desde ?? ""} a ${resultado.meses.at(-1)?.hasta ?? ""}`;
  const columnasBase = [columnaPrincipal, ...(resultado.por_terminal ? ["Terminal", "Nombre"] : [])];
  const base = (fila) => [fila[resultado.modo] ?? "", ...(resultado.por_terminal ? [fila.terminal ?? "", fila.nombre ?? ""] : [])];
  const campos = (filaMes) => [
    ...resultado.tipos.flatMap((tipo) => [Number(filaMes?.tipos?.[tipo] ?? 0), Number(filaMes?.promedios_por_tipo?.[tipo] ?? 0)]),
    Number(filaMes?.total_venta ?? 0), Number(filaMes?.promedio ?? 0),
  ];
  const encabezadosMes = resultado.tipos.flatMap((tipo) => [tipo, `Promedio ${tipo}`]).concat(["Venta total", "Promedio total"]);
  const excel = {
    titulo,
    periodo,
    encabezados: [...columnasBase, ...resultado.meses.flatMap((mes) => encabezadosMes.map((columna) => `${mes.etiqueta}${mes.periodo ? ` ${mes.periodo}` : ""} · ${columna}`))],
    filas: filas.map((fila) => [...base(fila), ...resultado.meses.flatMap((mes) => campos(fila.meses?.[mes.clave]))]),
    totales: ["Total", ...columnasBase.slice(1).map(() => ""), ...resultado.meses.flatMap((mes) => campos({ tipos: mes.totales.por_tipo, promedios_por_tipo: mes.totales.promedios_por_tipo, promedio: mes.totales.promedio, total_venta: mes.totales.total_venta }))],
  };
  const tablaPdf = {
    encabezados: [...columnasBase, ...resultado.meses.flatMap(() => encabezadosMes)],
    columnasFijas: columnasBase.length,
    gruposColumnas: [
      ...columnasBase.map((nombre) => ({ titulo: nombre, columnas: 1, fijo: true })),
      ...resultado.meses.map((mes) => ({ titulo: mes.periodo ? `${mes.etiqueta} · ${mes.periodo}` : mes.etiqueta, columnas: encabezadosMes.length })),
    ],
    filas: excel.filas,
    totales: excel.totales,
  };
  return { excel, pdf: { titulo, periodo, tablas: [tablaPdf] } };
}
