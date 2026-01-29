(function (wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar } = wp.editPost;
    const { PanelBody, Button, Spinner } = wp.components;
    const { useSelect } = wp.data;
    const { useState } = wp.element;
    const el = wp.element.createElement;

    const AAGLinkingSidebar = () => {
        const [suggestions, setSuggestions] = useState([]);
        const [isFetching, setIsFetching] = useState(false);
        const [error, setError] = useState(null);

        // Get post content
        const { content, title } = useSelect((select) => {
            const editor = select('core/editor');
            return {
                content: editor.getEditedPostContent(),
                title: editor.getEditedPostAttribute('title')
            };
        }, []);

        const fetchSuggestions = () => {
            setIsFetching(true);
            setError(null);

            jQuery.ajax({
                url: aagLinking.ajax_url,
                type: 'POST',
                data: {
                    action: 'aag_get_internal_links',
                    nonce: aagLinking.nonce,
                    content: content,
                    title: title
                },
                success: function (response) {
                    setIsFetching(false);
                    if (response.success) {
                        setSuggestions(response.data);
                    } else {
                        setError(response.data || 'Failed to fetch suggestions');
                    }
                },
                error: function () {
                    setIsFetching(false);
                    setError('An error occurred while connecting to the server.');
                }
            });
        };

        const insertLink = (link) => {
            const { insertBlock } = wp.data.dispatch('core/editor');
            const { createBlock } = wp.blocks;

            const newBlock = createBlock('core/paragraph', {
                content: `Read more: <a href="${link.url}">${link.title}</a>`
            });

            insertBlock(newBlock);
        };

        // UI Components as elements
        const suggestionList = suggestions.length > 0 ? el('ul', { style: { paddingLeft: '0', listStyleType: 'none' } },
            suggestions.map((suggestion, index) => el('li', { key: index, style: { marginBottom: '15px', padding: '10px', backgroundColor: '#f0f0f0', borderRadius: '4px' } },
                el('div', { style: { fontWeight: '600', marginBottom: '5px' } }, suggestion.title),
                el('div', { style: { fontSize: '11px', color: '#666', marginBottom: '8px', wordBreak: 'break-all' } }, suggestion.url),
                el(Button, { isSecondary: true, isSmall: true, onClick: () => insertLink(suggestion) }, 'Insert Link')
            ))
        ) : (!isFetching && !error ? el('p', { style: { fontStyle: 'italic', color: '#777' } }, 'No suggestions yet. Write some content and click the button!') : null);

        return el(PluginSidebar, {
            name: 'aag-linking-sidebar',
            title: 'RankReady AI Linking',
            icon: 'admin-links'
        }, el(PanelBody, { title: 'Internal Link Suggestions' },
            el('p', null, 'Analyze your content to find relevant internal links from your existing posts.'),
            el(Button, {
                isPrimary: true,
                onClick: fetchSuggestions,
                disabled: isFetching || !content
            }, isFetching ? el(Spinner, null) : 'Get Relevant Links'),
            error && el('div', { style: { marginTop: '15px', color: '#d94c53' } }, el('strong', null, 'Error: '), error),
            el('div', { style: { marginTop: '20px' } }, suggestionList)
        ));
    };

    registerPlugin('aag-linking', {
        render: AAGLinkingSidebar,
        icon: 'admin-links',
    });
})(window.wp);
