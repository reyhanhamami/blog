// Tab-local, user-scoped recovery of article body only; no tokens or form metadata.
export function articleDraft(owner, path, storage = () => window.sessionStorage, clock = Date.now) {
    const key = `besofton-article-draft:${owner}:${path}`;
    const ttl = 24 * 60 * 60 * 1000;
    return {
        read() {
            try {
                const value = JSON.parse(storage().getItem(key) || 'null');
                if (!value) return null;
                if (!Number.isFinite(value.expires) || value.expires <= clock() || typeof value.html !== 'string' || value.html.length > 1000000) {
                    storage().removeItem(key);
                    return null;
                }
                return value.html;
            } catch { return null; }
        },
        write(html) {
            try {
                if (html.length > 1000000) return false;
                storage().setItem(key, JSON.stringify({ html, expires: clock() + ttl }));
                return true;
            } catch { return false; }
        },
        remove() { try { storage().removeItem(key); } catch { /* Storage can be blocked. */ } },
    };
}
