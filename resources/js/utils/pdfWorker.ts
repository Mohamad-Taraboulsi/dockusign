import * as pdfjsLib from 'pdfjs-dist';

// Import worker source as raw text, then create a blob URL.
// This avoids .mjs MIME type issues on shared hosting (cPanel/Apache)
// where .mjs files are served as application/octet-stream.
import workerCode from 'pdfjs-dist/build/pdf.worker.mjs?raw';

const blob = new Blob([workerCode], { type: 'application/javascript' });
pdfjsLib.GlobalWorkerOptions.workerSrc = URL.createObjectURL(blob);

export { pdfjsLib };
