import { PDFDocument, rgb, StandardFonts, grayscale } from 'pdf-lib';

interface FlattenField {
    id: string;
    type: string;
    page_number: number;
    position_x: number;
    position_y: number;
    width: number;
    height: number;
    value: string | null;
}

export async function flattenPdf(
    pdfBase64: string,
    fields: FlattenField[],
    onProgress?: (progress: number) => void,
): Promise<Uint8Array> {
    const pdfBytes = Uint8Array.from(atob(pdfBase64), (c) => c.charCodeAt(0));
    const pdfDoc = await PDFDocument.load(pdfBytes);
    const pages = pdfDoc.getPages();
    const font = await pdfDoc.embedFont(StandardFonts.Helvetica);
    const boldFont = await pdfDoc.embedFont(StandardFonts.HelveticaBold);

    const totalFields = fields.filter((f) => f.value).length;
    let processedFields = 0;

    for (const field of fields) {
        if (!field.value) {
            continue;
        }

        const pageIndex = field.page_number - 1;
        if (pageIndex < 0 || pageIndex >= pages.length) {
            continue;
        }

        const page = pages[pageIndex];
        const { width, height } = page.getSize();

        const x = (field.position_x / 100) * width;
        const y =
            height -
            (field.position_y / 100) * height -
            (field.height / 100) * height;
        const w = (field.width / 100) * width;
        const h = (field.height / 100) * height;

        if (
            ['signature', 'initials', 'stamp'].includes(field.type) &&
            field.value.startsWith('data:image')
        ) {
            await embedAndDrawImage(pdfDoc, page, field.value, x, y, w, h);
        } else if (field.type === 'checkbox') {
            if (field.value === 'true') {
                drawCheckmark(page, x, y, w, h, font);
            }
        } else if (field.type === 'dropdown' || field.type === 'radio') {
            drawText(page, field.value, x, y, w, h, font, true);
        } else {
            drawText(page, field.value, x, y, w, h, font, false);
        }

        processedFields++;
        onProgress?.(Math.round((processedFields / totalFields) * 100));
    }

    return pdfDoc.save();
}

async function embedAndDrawImage(
    pdfDoc: PDFDocument,
    page: any,
    dataUri: string,
    x: number,
    y: number,
    w: number,
    h: number,
): Promise<void> {
    const parts = dataUri.split(',', 2);
    if (parts.length !== 2) return;

    const imageData = Uint8Array.from(atob(parts[1]), (c) => c.charCodeAt(0));

    let image;
    if (parts[0].includes('png')) {
        image = await pdfDoc.embedPng(imageData);
    } else if (parts[0].includes('jpeg') || parts[0].includes('jpg')) {
        image = await pdfDoc.embedJpg(imageData);
    } else {
        return;
    }

    page.drawImage(image, {
        x,
        y,
        width: w,
        height: h,
    });
}

function drawCheckmark(
    page: any,
    x: number,
    y: number,
    w: number,
    h: number,
    font: any,
): void {
    const checkColor = rgb(0, 0.47, 0);
    page.drawRectangle({
        x,
        y,
        width: w,
        height: h,
        borderColor: checkColor,
        borderWidth: 1,
    });

    const checkSize = Math.min(w, h) * 0.6;
    page.drawText('✓', {
        x: x + (w - checkSize) / 2,
        y: y + (h - checkSize) / 2,
        size: checkSize,
        font: font,
        color: checkColor,
    });
}

function drawText(
    page: any,
    value: string,
    x: number,
    y: number,
    w: number,
    h: number,
    font: any,
    isLabel: boolean,
): void {
    const fontSize = Math.max(6, Math.min(h * 0.5, 14));
    const textColor = rgb(0, 0, 0);

    page.drawRectangle({
        x,
        y,
        width: w,
        height: h,
        color: rgb(0.95, 0.95, 0.95),
    });

    page.drawText(value, {
        x: x + 3,
        y: y + (h - fontSize) / 2,
        size: fontSize,
        font: font,
        color: textColor,
        maxWidth: w - 6,
    });
}

export function downloadBlob(data: Uint8Array, filename: string): void {
    const blob = new Blob([new Uint8Array(data)], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
