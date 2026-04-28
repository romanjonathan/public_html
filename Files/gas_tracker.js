// Google Apps Script — Health Tracker API
//
// Sheet structure (single sheet, 3 columns):
//   A: Date  |  B: Weight (lbs)  |  C: Screen Time (hrs/day)
//
// Setup:
// 1. Go to script.google.com → New project → paste this file
// 2. Deploy → New deployment → Web app
//      Execute as: Me
//      Who has access: Anyone
// 3. Paste the deployment URL into health_tracker.php

const SHEET_ID = '1Rzio_BmpHrqCwWwh2IfbWPEKOrqC-NHmebITni7D3ks';

function getSheet() {
  return SpreadsheetApp.openById(SHEET_ID).getSheets()[0];
}

function doGet(e) {
  const n    = parseInt(e.parameter.n) || 16;
  const data = getSheet().getDataRange().getValues();
  const rows = data.slice(1)
    .filter(row => row[0])
    .sort((a, b) => new Date(a[0]) - new Date(b[0]))
    .slice(-n);

  const result = rows.map(row => ({
    date:        Utilities.formatDate(new Date(row[0]), Session.getScriptTimeZone(), 'yyyy-MM-dd'),
    weight:      row[1],
    screentime:  row[3]  // col D: decimal hours
  }));

  return ContentService
    .createTextOutput(JSON.stringify(result))
    .setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  const p = JSON.parse(e.postData.contents);
  getSheet().appendRow([new Date(p.date), parseFloat(p.weight), p.hhmm, parseFloat(p.screentime)]);

  return ContentService
    .createTextOutput(JSON.stringify({ success: true }))
    .setMimeType(ContentService.MimeType.JSON);
}
