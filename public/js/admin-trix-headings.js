// Override Trix heading level: h1 → h3
document.addEventListener('trix-initialize', function () {
    if (typeof Trix !== 'undefined') {
        Trix.config.blockAttributes.heading1.tagName = 'h3';
    }
});
