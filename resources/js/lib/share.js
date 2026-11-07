/**
 * Membagikan PO lewat menu bagikan HP. Kalau penjual sudah mengunggah gambar story,
 * gambarnya ikut dibagikan supaya bisa langsung dipasang di story Instagram atau WhatsApp.
 */
export async function sharePreorder({ title, shopName, url, storyUrl }) {
    const link = `${window.location.origin}${url}`;
    const text = `${title} dari ${shopName}. Pesan di Jajapo: ${link}`;

    if (navigator.share) {
        let files;
        if (storyUrl && navigator.canShare) {
            try {
                const blob = await (await fetch(storyUrl)).blob();
                const ext = blob.type.split('/')[1] || 'jpg';
                const file = new File([blob], `story-${url.split('/').pop()}.${ext}`, { type: blob.type });
                if (navigator.canShare({ files: [file] })) files = [file];
            } catch {
                // gambar gagal diambil, bagikan tautannya saja
            }
        }
        try {
            await navigator.share(files ? { files, text } : { title, text, url: link });
        } catch {
            // dibatalkan
        }
        return;
    }

    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
}
