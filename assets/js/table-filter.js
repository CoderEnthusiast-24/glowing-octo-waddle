// DermAve IMS - search + status filter for any table with class "js-filter-table"
// (used by the Products, Machines and Supplies pages)

const searchInput = document.getElementById("tableSearch");
const statusFilter = document.getElementById("statusFilter"); // optional
const noResults = document.getElementById("noResults");
const rows = document.querySelectorAll(".js-filter-table tbody tr");

// Show only the rows that match the search text and the chosen status
function applyFilters() {
  const text = searchInput.value.trim().toLowerCase();
  const status = statusFilter ? statusFilter.value : "all";
  let visible = 0;

  rows.forEach((row) => {
    const matchesText = row.textContent.toLowerCase().includes(text);
    const matchesStatus = status === "all" || row.dataset.status === status;
    const show = matchesText && matchesStatus;
    row.hidden = !show;
    if (show) visible++;
  });

  noResults.hidden = visible > 0;
}

if (searchInput) searchInput.addEventListener("input", applyFilters);
if (statusFilter) statusFilter.addEventListener("change", applyFilters);

// TODO: add "Add / Edit / Delete" actions, and load rows from the database
