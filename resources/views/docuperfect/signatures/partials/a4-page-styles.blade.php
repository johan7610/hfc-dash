{{-- Shared A4 page rendering: CSS + JS split function --}}
<style>
.corex-a4-page {
    width: 210mm;
    min-height: 297mm;
    max-width: 100%;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 0 auto 24px auto;
    padding: 20mm 18mm 25mm 18mm;
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
}
.corex-a4-page .page-number {
    position: absolute;
    bottom: 10mm;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 9px;
    color: #94a3b8;
}
.corex-page-gap {
    height: 24px;
    background: #f1f5f9;
    margin: 0 auto;
    width: 210mm;
    max-width: 100%;
}
/* Kill inner container styling when A4 pages are active */
.corex-a4-page .corex-document-wrapper,
.corex-a4-page .corex-page {
    width: 100% !important;
    max-width: 100% !important;
    min-height: auto !important;
    box-shadow: none !important;
    background: transparent !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    border-radius: 0 !important;
}
/* Also for when split hasn't run yet but document is in a page container */
#webDocContent .corex-document-wrapper,
#webDocContent .corex-page,
[x-ref="webDocContent"] .corex-document-wrapper,
[x-ref="webDocContent"] .corex-page {
    width: 100% !important;
    max-width: 100% !important;
    box-shadow: none !important;
    border-radius: 0 !important;
}
@media print {
    .corex-a4-page {
        box-shadow: none;
        margin: 0;
        padding: 20mm 18mm;
        page-break-after: always;
        min-height: auto;
    }
    .corex-a4-page:last-child {
        page-break-after: avoid;
    }
    .corex-page-gap {
        display: none;
    }
    .corex-page-break {
        border: none !important;
        margin: 0 !important;
    }
    .corex-page-break > div:last-child {
        display: none !important;
    }
}
</style>
<script>
function splitDocumentIntoPages(container) {
    if (!container) return;
    // Guard against double invocation
    if (container.dataset.pagesSplit === 'true') return;
    container.dataset.pagesSplit = 'true';

    // Strategy 1: Template already has .corex-page divs (paged templates).
    // These are the template's own page structure — each becomes an A4 page.
    var wrapper = container.querySelector('.corex-document-wrapper');
    var corexPages = wrapper
        ? wrapper.querySelectorAll(':scope > .corex-page')
        : container.querySelectorAll(':scope > .corex-page');

    if (corexPages.length > 1) {
        // Collect any <style> tags that are siblings (keep them)
        var styles = [];
        Array.from(container.children).forEach(function(el) {
            if (el.tagName === 'STYLE' || el.tagName === 'LINK') styles.push(el);
        });

        // Extract pages from the wrapper
        var pageEls = Array.from(corexPages);
        container.innerHTML = '';

        // Re-add styles
        styles.forEach(function(s) { container.appendChild(s); });

        var totalPages = pageEls.length;
        pageEls.forEach(function(pageEl, idx) {
            var pageDiv = document.createElement('div');
            pageDiv.className = 'corex-a4-page';
            // Move all children from the .corex-page into the A4 wrapper
            while (pageEl.firstChild) {
                pageDiv.appendChild(pageEl.firstChild);
            }

            // Page number footer
            var pageNum = document.createElement('div');
            pageNum.className = 'page-number';
            pageNum.textContent = 'Page ' + (idx + 1) + ' of ' + totalPages;
            pageDiv.appendChild(pageNum);

            container.appendChild(pageDiv);

            // Gap between pages (not after last)
            if (idx < totalPages - 1) {
                var gap = document.createElement('div');
                gap.className = 'corex-page-gap';
                container.appendChild(gap);
            }
        });
        return;
    }

    // Strategy 2: Continuous HTML with .corex-page-break markers.
    // Breaks may be nested inside wrapper divs — unwrap first.
    var breaks = container.querySelectorAll('.corex-page-break');
    if (breaks.length === 0) return;

    // If breaks are not direct children, unwrap from .corex-document-wrapper / .corex-page
    var firstBreak = breaks[0];
    if (firstBreak.parentElement !== container) {
        // Find the deepest single-child wrapper and unwrap content to container level
        var contentSource = container;
        var innerWrapper = container.querySelector('.corex-document-wrapper');
        if (innerWrapper) contentSource = innerWrapper;
        var innerPage = contentSource.querySelector(':scope > .corex-page');
        if (innerPage) contentSource = innerPage;

        // Collect styles before unwrapping
        var styleEls = [];
        Array.from(container.children).forEach(function(el) {
            if (el.tagName === 'STYLE' || el.tagName === 'LINK') styleEls.push(el.cloneNode(true));
        });

        // Move all children from the inner container up to the main container
        container.innerHTML = '';
        styleEls.forEach(function(s) { container.appendChild(s); });
        while (contentSource.firstChild) {
            container.appendChild(contentSource.firstChild);
        }
    }

    // Now split on .corex-page-break markers (which are now direct children)
    var allNodes = Array.from(container.childNodes);
    var pages = [];
    var currentPageNodes = [];

    allNodes.forEach(function(node) {
        if (node.nodeType === 1 && node.classList && node.classList.contains('corex-page-break')) {
            // Page break marker belongs to current page (initials at bottom)
            currentPageNodes.push(node);
            pages.push(currentPageNodes);
            currentPageNodes = [];
        } else {
            currentPageNodes.push(node);
        }
    });

    // Last page (content after final break)
    if (currentPageNodes.length > 0) {
        pages.push(currentPageNodes);
    }

    // Clear container and rebuild with A4 page wrappers
    container.innerHTML = '';
    var totalPages = pages.length;

    pages.forEach(function(pageNodes, idx) {
        var pageDiv = document.createElement('div');
        pageDiv.className = 'corex-a4-page';
        pageNodes.forEach(function(n) { pageDiv.appendChild(n); });

        // Page number footer
        var pageNum = document.createElement('div');
        pageNum.className = 'page-number';
        pageNum.textContent = 'Page ' + (idx + 1) + ' of ' + totalPages;
        pageDiv.appendChild(pageNum);

        container.appendChild(pageDiv);

        // Gap between pages (not after last)
        if (idx < totalPages - 1) {
            var gap = document.createElement('div');
            gap.className = 'corex-page-gap';
            container.appendChild(gap);
        }
    });
}
</script>
