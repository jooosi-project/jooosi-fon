document.addEventListener('DOMContentLoaded', async function () {
    // replace the content #font-family-group>div>textarea with the jooosiFonts in json string
    document.querySelector('#font-family-group>div>textarea').value = JSON.stringify(jooosiFonts);
});