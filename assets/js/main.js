// DermAve IMS - behavior shared by every logged-in page

// Nav links for pages that are not built yet
document.querySelectorAll('#mainNav a[data-soon]').forEach((link) => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    alert('This page is not built yet.');
  });
});
