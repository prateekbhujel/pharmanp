import { http } from '../api/http';

function filenameFromDisposition(disposition, fallback = 'pharmanp-document') {
    const match = String(disposition || '').match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);

    return match ? decodeURIComponent(match[1].replace(/"/g, '')) : fallback;
}

export async function openAuthenticatedDocument(url, options = {}) {
    if (!url) {
        return null;
    }

    const opened = window.open('', '_blank', 'noopener,noreferrer');

    try {
        const response = await http.get(url, {
            responseType: 'blob',
            headers: { Accept: options.accept || 'text/html,application/pdf,*/*' },
        });
        const type = response.headers?.['content-type'] || options.type || 'application/octet-stream';
        
        if (type.includes('text/html')) {
            const html = await response.data.text();
            const targetWindow = opened || window.open('', '_blank');
            if (targetWindow) {
                targetWindow.document.open();
                targetWindow.document.write(html);
                targetWindow.document.close();
            }
            return opened;
        }

        const blob = new Blob([response.data], { type });
        const objectUrl = window.URL.createObjectURL(blob);

        if (opened) {
            opened.location.href = objectUrl;
        } else {
            const anchor = document.createElement('a');
            anchor.href = objectUrl;
            anchor.target = '_blank';
            anchor.rel = 'noopener noreferrer';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
        }

        window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 60_000);

        return opened;
    } catch (error) {
        opened?.close();
        throw error;
    }
}

export async function downloadAuthenticatedDocument(url, fallbackName = 'pharmanp-export') {
    const response = await http.get(url, {
        responseType: 'blob',
        headers: { Accept: 'application/octet-stream,*/*' },
    });
    const type = response.headers?.['content-type'] || 'application/octet-stream';
    const blob = new Blob([response.data], { type });
    const objectUrl = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = objectUrl;
    anchor.download = filenameFromDisposition(response.headers?.['content-disposition'], fallbackName);
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();

    window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 60_000);
}
