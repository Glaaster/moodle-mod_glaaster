// Using YUI to manipulate the DOM
YUI().use("node", "event", function(Y) {
    const frameYUI = Y.one("#contentframe");

    // Dynamically add the loader to the DOM
    const loaderHTML = '' +
        '<div id="loader"' +
        'style="position: absolute; top: 0; bottom: 0; left: 0; right: 0; background: rgba(255,255,255,0.8); z-index: 1000;' +
        'display: flex; justify-content: center; align-items: center;">' +
        '<div class="spinner" style="border: 8px solid #f3f3f3; border-top: 8px solid rgb(123,80,223); border-radius: 50%;' +
        'width: 60px; height: 60px; animation: spin 2s linear infinite;"></div>' +
        '</div>';

    // Inject the loader into the DOM
    Y.one("div[role='main']").append(loaderHTML);

    // Add keyframes CSS for the animation
    const style = document.createElement('style');
    style.innerHTML = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
    document.getElementsByTagName('head')[0].appendChild(style);

    const loader = Y.one("#loader");
    loader.setStyle('display', 'flex');

    // Display the loader when the iframe starts loading
    frameYUI.on('load', function() {
        loader.setStyle('display', 'none');
    });
});
