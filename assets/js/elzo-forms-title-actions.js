/**
 * Elzo Forms heading buttons.
 *
 * Places the Import and Export buttons after the screen heading and its
 * "Add New" button, where WordPress shows the actions of a screen.
 */
(function () {
    'use strict';

    var actions = (window.ElzoFormsTitleActions || {}).actions || [];

    function insert() {
        var heading = document.querySelector('.wrap > .wp-heading-inline');
        if (!heading || !actions.length) {
            return;
        }

        var after = heading;
        var sibling = heading.nextElementSibling;
        while (sibling && sibling.classList.contains('page-title-action')) {
            after = sibling;
            sibling = sibling.nextElementSibling;
        }

        actions.forEach(function (action) {
            var link = document.createElement('a');
            link.className = 'page-title-action';
            link.href = action.url;
            link.textContent = action.label;
            if (action.title) {
                link.title = action.title;
            }

            // WordPress separates the heading buttons with whitespace.
            after.parentNode.insertBefore(document.createTextNode(' '), after.nextSibling);
            after.parentNode.insertBefore(link, after.nextSibling.nextSibling);
            after = link;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', insert);
    } else {
        insert();
    }
})();
