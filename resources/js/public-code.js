import Prism from 'prismjs';
import 'prismjs/themes/prism-tomorrow.min.css';

const loaders = {
    php: async () => { await import('prismjs/components/prism-markup-templating'); await import('prismjs/components/prism-php'); },
    typescript: () => import('prismjs/components/prism-typescript'),
    bash: () => import('prismjs/components/prism-bash'),
    json: () => import('prismjs/components/prism-json'),
    sql: () => import('prismjs/components/prism-sql'),
    python: () => import('prismjs/components/prism-python'),
    go: () => import('prismjs/components/prism-go'),
};
const highlighted = new WeakSet();

export async function highlightCode(root) {
    if (!root?.isConnected) return;
    const blocks = [...root.querySelectorAll('pre code')].filter(block => !highlighted.has(block));
    blocks.forEach(block => highlighted.add(block));
    const languages = new Set(blocks.map(block => [...block.classList].find(name => name.startsWith('language-'))?.slice(9)));
    await Promise.all([...languages].map(language => loaders[language]?.()));
    blocks.filter(block => block.isConnected).forEach(block => Prism.highlightElement(block));
}
