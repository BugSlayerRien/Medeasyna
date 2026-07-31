// JavaScript to toggle visibility of all notifications
document.getElementById('seeMoreLink').addEventListener('click', function(event) {
    event.preventDefault();  // Prevent the default link behavior
    var notificationSection = document.getElementById('notifications');

    // Toggle the expanded class
    notificationSection.classList.toggle('expanded');

    // Change the link text depending on the state
    var linkText = notificationSection.classList.contains('expanded') ? 'Show less' : 'See more';
    document.getElementById('seeMoreLink').textContent = linkText;
});
