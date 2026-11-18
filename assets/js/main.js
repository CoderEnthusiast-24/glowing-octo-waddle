// DermAve IMS - behavior shared by every logged-in page

// Nav links for pages that are not built yet
document.querySelectorAll('#mainNav a[data-soon]').forEach((link) => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    alert('This page is not built yet.');
  });
});

// If the browser restores this page from the back/forward cache (Back button),
// reload it so the server checks the session again. After logout or a timeout
// this sends the visitor to the login page instead of showing the old page.
window.addEventListener('pageshow', (e) => {
  if (e.persisted) location.reload();
});