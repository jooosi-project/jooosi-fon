import * as csstree from 'css-tree';

const ISOLATION_CLASS = 'jooosi-fon-style';
const PORTAL_CLASS = 'jooosi-fon-portal';
const PORTAL_ATTRIBUTE = 'data-base-ui-portal';
const LEGACY_PSEUDO_ELEMENTS = new Set(['before', 'after', 'first-letter', 'first-line']);
const WORDPRESS_STYLESHEET_SELECTOR = [
    'link[rel="stylesheet"][href*="wp-admin/load-styles.php"]',
    'link[rel="stylesheet"][href*="wp-admin/css/colors/"]',
].join(', ');

type JooosiFonDocumentBody = HTMLElement & { jooosiFonPortalObserver?: MutationObserver };

function exclusionNode(): csstree.CssNode {
    const selector = csstree.parse(`:not(.${ISOLATION_CLASS} *)`, { context: 'selector' }) as csstree.Selector;
    const node = selector.children.first;

    if (!node) {
        throw new Error('Could not create the Jooosi Fon style exclusion selector.');
    }

    return node;
}

/**
 * Make every WordPress selector explicitly ignore descendants of the React
 * application. Pseudo-elements must remain last in their compound selector.
 */
export function isolateWordPressCss(css: string): string {
    const ast = csstree.parse(css);
    const exclusion = exclusionNode();
    const selectors: csstree.Selector[] = [];

    csstree.walk(ast, {
        enter(node: csstree.CssNode) {
            if (node.type === 'Atrule' && node.name.toLowerCase() === 'keyframes') {
                return csstree.walk.skip;
            }

            if (node.type === 'Selector') selectors.push(node);
        },
    });

    selectors.forEach((selector) => {
        let inserted = false;

        selector.children.forEach((child, item, list) => {
            const legacyPseudoElement = child.type === 'PseudoClassSelector'
                && LEGACY_PSEUDO_ELEMENTS.has(child.name.toLowerCase());

            if (!inserted && (child.type === 'PseudoElementSelector' || legacyPseudoElement)) {
                list.insertData(csstree.clone(exclusion), item);
                inserted = true;
            }
        });

        if (!inserted) selector.children.push(csstree.clone(exclusion));
    });

    return csstree.generate(ast);
}

function protectPortal(node: Node): void {
    if (!(node instanceof HTMLElement)) return;

    if (node.hasAttribute(PORTAL_ATTRIBUTE)) {
        node.classList.add(ISOLATION_CLASS, PORTAL_CLASS);
    }

    node.querySelectorAll<HTMLElement>(`[${PORTAL_ATTRIBUTE}]`).forEach((portal) => {
        portal.classList.add(ISOLATION_CLASS, PORTAL_CLASS);
    });
}

async function isolateStylesheet(link: HTMLLinkElement): Promise<void> {
    try {
        const response = await fetch(link.href, { credentials: 'same-origin' });
        if (!response.ok) return;

        const style = document.createElement('style');
        style.dataset.jooosiFonWpAdminIsolation = link.href;
        style.media = link.media;
        style.nonce = link.nonce;
        style.textContent = isolateWordPressCss(await response.text());
        link.replaceWith(style);
    } catch {
        // Keep the original stylesheet active if fetching or parsing fails.
    }
}

/**
 * Isolate the React application from WordPress admin CSS while leaving the
 * admin menu, toolbar, notices, Media Library, and WordPress-owned layers intact.
 */
export async function isolateWordPressAdminStyles(root: HTMLElement): Promise<void> {
    root.classList.add(ISOLATION_CLASS);
    document.querySelectorAll<HTMLElement>(`[${PORTAL_ATTRIBUTE}]`).forEach(protectPortal);

    const body = document.body as JooosiFonDocumentBody;
    if (!body.jooosiFonPortalObserver) {
        body.jooosiFonPortalObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => mutation.addedNodes.forEach(protectPortal));
        });
        body.jooosiFonPortalObserver.observe(body, { childList: true });
    }

    const links = Array.from(document.querySelectorAll<HTMLLinkElement>(WORDPRESS_STYLESHEET_SELECTOR));
    await Promise.all(links.map(isolateStylesheet));
}
