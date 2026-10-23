/*
 * Scroll highlight for the dashboard sidebar.
 * While you scroll, the sidebar link of the section you are reading is highlighted.
 * Clicking a link scrolls smoothly to that section.
 */
(function () {
    var links = Array.prototype.slice.call(document.querySelectorAll('.sb-nav a[href^="#"]'));
    if (links.length === 0) return;

    var sections = [];
    links.forEach(function (a) {
        var sec = document.getElementById(a.getAttribute('href').slice(1));
        if (sec) sections.push(sec);
    });
    if (sections.length === 0) return;

    var lockUntil = 0;   // after a click, ignore scroll updates while the page is still scrolling

    function setActive(id) {
        links.forEach(function (a) {
            a.classList.toggle('active', a.getAttribute('href') === '#' + id);
        });
    }

    function update() {
        if (Date.now() < lockUntil) return;

        // A section becomes the current one when its top passes this line (px from the top of the screen)
        var line = 140;
        var current = sections[0];
        sections.forEach(function (sec) {
            if (sec.getBoundingClientRect().top <= line) current = sec;
        });

        // At the very bottom of the page, always highlight the last section
        var atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
        if (atBottom) current = sections[sections.length - 1];

        setActive(current.id);
    }

    links.forEach(function (a) {
        a.addEventListener('click', function () {
            setActive(a.getAttribute('href').slice(1));
            lockUntil = Date.now() + 900;
            setTimeout(update, 950);
        });
    });

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
})();
