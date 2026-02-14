/**
 * Clickable star rating for SeoKeyword relevanceScore.
 * Works on EasyAdmin index pages via event delegation.
 */
document.addEventListener('click', function(e) {
    var star = e.target.closest('.seo-star');
    if (!star) return;

    var group = star.closest('.seo-star-group');
    if (!group) return;

    var newScore = parseInt(star.dataset.value, 10);
    var currentScore = parseInt(group.dataset.score, 10);

    // Click on the same score = toggle to 0
    if (newScore === currentScore) {
        newScore = 0;
    }

    var url = group.dataset.url;
    var token = group.dataset.token;

    // Optimistic UI update
    updateStars(group, newScore);

    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=' + encodeURIComponent(token) + '&score=' + newScore
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            group.dataset.score = data.score;
            updateStars(group, data.score);
        } else {
            // Revert on error
            updateStars(group, currentScore);
        }
    })
    .catch(function() {
        updateStars(group, currentScore);
    });
});

// Hover preview
document.addEventListener('mouseover', function(e) {
    var star = e.target.closest('.seo-star');
    if (!star) return;
    var group = star.closest('.seo-star-group');
    if (!group) return;
    var hoverValue = parseInt(star.dataset.value, 10);
    previewStars(group, hoverValue);
});

document.addEventListener('mouseout', function(e) {
    var star = e.target.closest('.seo-star');
    if (!star) return;
    var group = star.closest('.seo-star-group');
    if (!group) return;
    var score = parseInt(group.dataset.score, 10);
    updateStars(group, score);
});

function updateStars(group, score) {
    var stars = group.querySelectorAll('.seo-star');
    var questionMark = group.querySelector('span');
    stars.forEach(function(s) {
        var val = parseInt(s.dataset.value, 10);
        if (val <= score) {
            s.className = 'fas fa-star seo-star';
            s.style.color = '#f59e0b';
        } else {
            s.className = 'far fa-star seo-star';
            s.style.color = '#d1d5db';
        }
    });
    if (questionMark) {
        questionMark.style.display = score === 0 ? '' : 'none';
    }
}

function previewStars(group, hoverValue) {
    var stars = group.querySelectorAll('.seo-star');
    stars.forEach(function(s) {
        var val = parseInt(s.dataset.value, 10);
        if (val <= hoverValue) {
            s.className = 'fas fa-star seo-star';
            s.style.color = '#fbbf24';
        } else {
            s.className = 'far fa-star seo-star';
            s.style.color = '#e5e7eb';
        }
    });
}
