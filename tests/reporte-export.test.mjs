import test from "node:test";
import assert from "node:assert/strict";
import pdfMake from "pdfmake/build/pdfmake.js";
import fuentes from "pdfmake/build/vfs_fonts.js";
import { crearExcel, definicionPdf, tablaVentas, tablasPeriodo } from "../resources/js/reporte-export.js";

async function entradaZip(blob, ruta) {
  const bytes = new Uint8Array(await blob.arrayBuffer());
  const vista = new DataView(bytes.buffer);
  const decodificar = new TextDecoder();
  let posicion = 0;

  while (posicion + 30 < bytes.length && vista.getUint32(posicion, true) === 0x04034b50) {
    const longitudNombre = vista.getUint16(posicion + 26, true);
    const longitudExtra = vista.getUint16(posicion + 28, true);
    const tamano = vista.getUint32(posicion + 18, true);
    const nombre = decodificar.decode(bytes.slice(posicion + 30, posicion + 30 + longitudNombre));
    const inicio = posicion + 30 + longitudNombre + longitudExtra;
    if (nombre === ruta) return decodificar.decode(bytes.slice(inicio, inicio + tamano));
    posicion = inicio + tamano;
  }

  throw new Error(`No existe ${ruta} en el archivo Excel`);
}

test("Excel contiene montos numéricos y texto escapado", async () => {
  const excel = crearExcel({
    titulo: "Ventas <Romana>",
    periodo: "2026-09-01 a 2026-10-18",
    encabezados: ["Subgrupo", "Venta total"],
    filas: [["18-Romana", 1234.5], ["=SUM(A1)", 20]],
    totales: ["Total", 1254.5],
  });
  const hoja = await entradaZip(excel, "xl/worksheets/sheet1.xml");
  const estilos = await entradaZip(excel, "xl/styles.xml");

  assert.equal(excel.type, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
  assert.match(hoja, /Ventas &lt;Romana&gt;/);
  assert.match(hoja, /<c r="B4" s="1"><v>1234.5<\/v><\/c>/);
  assert.match(hoja, /<c r="A5" t="inlineStr"><is><t>=SUM\(A1\)<\/t><\/is><\/c>/);
  assert.match(estilos, /applyNumberFormat="1"/);
});

test("ventas conserva todas las filas y totales al preparar la exportación", () => {
  const resultado = {
    tipos: ["Tradicional", "Raza"],
    totales: { por_tipo: { Tradicional: 30, Raza: 5 }, promedios_por_tipo: { Tradicional: 15, Raza: 5 }, promedio: 17.5, total_venta: 35 },
  };
  const filas = [
    { subgrupo: "Romana", tipos: { Tradicional: 10, Raza: 5 }, promedios_por_tipo: { Tradicional: 10, Raza: 5 }, promedio: 15, total_venta: 15 },
    { subgrupo: "Nagua", tipos: { Tradicional: 20 }, promedios_por_tipo: { Tradicional: 20 }, promedio: 20, total_venta: 20 },
  ];
  const tabla = tablaVentas(resultado, [{ key: "subgrupo", label: "Subgrupo" }], filas, "Ventas", "Septiembre 2026");

  assert.equal(tabla.filas.length, 2);
  assert.deepEqual(tabla.filas[1], ["Nagua", 20, 20, 0, 0, 20, 20]);
  assert.equal(tabla.totales.at(-1), 35);
});

test("período conserva ambos meses en columnas de Excel y PDF", async () => {
  const total = (venta) => ({ por_tipo: { Tradicional: venta }, promedios_por_tipo: { Tradicional: venta }, promedio: venta, total_venta: venta });
  const resultado = {
    modo: "subgrupo",
    por_terminal: false,
    tipos: ["Tradicional"],
    meses: [
      { clave: "2026-09", etiqueta: "Septiembre 2026", desde: "2026-09-01", hasta: "2026-09-30", totales: total(10) },
      { clave: "2026-10", etiqueta: "Octubre 2026", desde: "2026-10-01", hasta: "2026-10-18", totales: total(20) },
    ],
  };
  const filas = [{ subgrupo: "Romana", meses: { "2026-09": { tipos: { Tradicional: 10 }, promedios_por_tipo: { Tradicional: 10 }, promedio: 10, total_venta: 10 }, "2026-10": { tipos: { Tradicional: 20 }, promedios_por_tipo: { Tradicional: 20 }, promedio: 20, total_venta: 20 } } }];
  const preparado = tablasPeriodo(resultado, "Subgrupo", filas, "Ventas por período");

  assert.equal(preparado.excel.encabezados.length, 9);
  assert.deepEqual(preparado.excel.filas[0], ["Romana", 10, 10, 10, 10, 20, 20, 20, 20]);
  assert.equal(preparado.pdf.tablas.length, 1);
  assert.equal(preparado.pdf.tablas[0].filas[0].at(-1), 20);
  const tablaPdf = definicionPdf(preparado.pdf).content.find((parte) => parte.table).table;
  assert.equal(tablaPdf.headerRows, 2);
  assert.equal(tablaPdf.body[0][1].text, "Septiembre 2026");
  assert.equal(tablaPdf.body[0][5].text, "Octubre 2026");
  pdfMake.vfs = fuentes.pdfMake.vfs;
  const buffer = await new Promise((resolver) => pdfMake.createPdf(definicionPdf(preparado.pdf)).getBuffer(resolver));
  assert.equal(Buffer.from(buffer).subarray(0, 4).toString(), "%PDF");
});

test("PDF se genera con datos y encabezados", async () => {
  pdfMake.vfs = fuentes.pdfMake.vfs;
  const encabezados = ["Subgrupo", ...Array.from({ length: 17 }, (_, indice) => `Promedio producto ${indice + 1}`)];
  const documento = definicionPdf({
    titulo: "Ventas por subgrupo",
    periodo: "Septiembre 2026",
    tablas: [{ titulo: "Romana", encabezados, filas: [["18-Romana", ...Array(17).fill(123.5)]], totales: ["Total", ...Array(17).fill(123.5)] }],
  });
  const buffer = await new Promise((resolver) => pdfMake.createPdf(documento).getBuffer(resolver));

  assert.equal(Buffer.from(buffer).subarray(0, 4).toString(), "%PDF");
});
