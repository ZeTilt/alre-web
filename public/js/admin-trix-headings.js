// Override Trix heading level: h1 → h3
// trix-before-initialize fires after Trix is loaded but before editors are created
document.addEventListener('trix-before-initialize', function () {
    Trix.config.blockAttributes.heading1.tagName = 'h3';
});
