(function () {
    const etchConfig = window.jooosiFonEtch || {};
    const fontFamilies = Array.isArray(etchConfig.font_families) ? etchConfig.font_families : [];

    const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    let lastEditorView = null;
    let isAutocompleteSettingUp = false;
    let pendingAutocompleteRefresh = false;
    let autocompleteRefreshTimer = null;

    const findAutocompletionConfig = (editorView) => {
        const staticValues = editorView?.state?.config?.staticValues;

        if (!Array.isArray(staticValues)) {
            return null;
        }

        for (let i = 0; i < staticValues.length; i++) {
            const val = staticValues[i];
            if (
                val
                && typeof val === 'object'
                && Array.isArray(val.override)
                && val.activateOnTyping !== undefined
                && val.maxRenderedOptions !== undefined
            ) {
                return val;
            }
        }

        return null;
    };

    const buildFontItems = () => fontFamilies
        .filter((font) => font && font.key && font.name)
        .map((font) => ({
            label: `Jooosi Fon - ${font.name}`,
            type: 'variable',
            apply: `font-family: ${font.key};`,
            detail: '[Jooosi Fon]',
        }));

    const createFontCompletionSource = (fontItems) => function jooosiFonEtchFontCompletionSource(context) {
        const word = context.matchBefore(/[\w-]*/);

        if (!word || (word.from === word.to && !context.explicit)) {
            return null;
        }

        const typed = word.text.toLowerCase();
        const filtered = fontItems.filter(item => item.label.toLowerCase().includes(typed));

        if (filtered.length === 0) {
            return null;
        }

        return {
            from: word.from,
            options: filtered,
            validFor: /^[\w-]*$/,
        };
    };

    const registerAutocomplete = (editorView) => {
        if (!editorView || lastEditorView === editorView) {
            return;
        }

        const autocompletionConfig = findAutocompletionConfig(editorView);

        if (!autocompletionConfig) {
            return;
        }

        if (!window.__jooosiFonEtchFontCompletionSource) {
            const fontItems = buildFontItems();
            if (!fontItems.length) {
                return;
            }
            window.__jooosiFonEtchFontCompletionSource = createFontCompletionSource(fontItems);
        }

        const completionSource = window.__jooosiFonEtchFontCompletionSource;

        if (!autocompletionConfig.override.includes(completionSource)) {
            autocompletionConfig.override.push(completionSource);
        }

        lastEditorView = editorView;
    };


    const waitForCssEditor = async (maxAttempts = 300, interval = 100) => {
        let attempts = 0;
        let contentEl = document.querySelector('.etch-css-editor .cm-editor .cm-content');

        while (attempts < maxAttempts) {
            if (contentEl && contentEl.cmView) {
                return contentEl;
            }

            await sleep(interval);
            contentEl = document.querySelector('.etch-css-editor .cm-editor .cm-content');
            attempts += 1;
        }

        return null;
    };

    const setupAutocomplete = async () => {
        if (!fontFamilies.length) {
            return;
        }

        if (isAutocompleteSettingUp) {
            pendingAutocompleteRefresh = true;
            return;
        }

        isAutocompleteSettingUp = true;

        try {
            const contentEl = await waitForCssEditor();

            if (!contentEl || !contentEl.cmView) {
                return;
            }

            const editorView = contentEl.cmView.rootView && contentEl.cmView.rootView.view
                ? contentEl.cmView.rootView.view
                : null;

            if (!editorView) {
                return;
            }

            registerAutocomplete(editorView);
        } finally {
            isAutocompleteSettingUp = false;
            if (pendingAutocompleteRefresh) {
                pendingAutocompleteRefresh = false;
                setupAutocomplete();
            }
        }
    };

    const scheduleAutocompleteRefresh = () => {
        if (autocompleteRefreshTimer) {
            return;
        }

        autocompleteRefreshTimer = setTimeout(() => {
            autocompleteRefreshTimer = null;
            setupAutocomplete();
        }, 150);
    };

    const hasEditorNodes = (nodes) => Array.from(nodes).some((node) => {
        if (!node || node.nodeType !== 1) {
            return false;
        }

        const element = node;
        if (element.matches('.cm-editor, .etch-css-editor, .cm-content')) {
            return true;
        }

        return typeof element.querySelector === 'function'
            ? element.querySelector('.cm-editor, .etch-css-editor, .cm-content')
            : false;
    });

    const observeCssEditor = () => {
        const observer = new MutationObserver((mutations) => {
            const shouldRefresh = mutations.some((mutation) => (
                hasEditorNodes(mutation.addedNodes) || hasEditorNodes(mutation.removedNodes)
            ));

            if (shouldRefresh) {
                scheduleAutocompleteRefresh();
            }
        });

        observer.observe(document, {
            subtree: true,
            childList: true,
        });
    };

    const init = () => {
        setupAutocomplete();
        observeCssEditor();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
