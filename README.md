# DermAve IMS

Web-based Inventory Management System with Barcode Scanner for **DermAve Medical Aesthetic & Wellness Clinic**.

A group project for a dermatology study.

## Tech stack

- HTML
- CSS
- JavaScript
- PHP

## Theme

Based on the DermAve logo: black, gold (`#ffe066`) and soft white.

## Project structure

```
dermave-ims/
├── index.php            # redirects to login
├── login.php            # login page
├── logout.php           # ends the session
├── dashboard.php        # dashboard page
├── directory.php        # unified directory (all items, Add / Edit / Archive)
├── directory_api.php    # saves Add / Edit / Archive / Restore to MySQL (JSON)
├── products.php         # products page (item, batch, expiry, qty, status)
├── machines.php         # machines page (maintenance dates + status)
├── supplies.php         # supplies page (low stock, out of stock, stock list)
├── includes/
│   ├── config.php       # app + database settings (placeholder)
│   ├── auth.php         # protects pages that need login
│   ├── header.php       # shared top bar + navigation
│   ├── footer.php       # shared scripts + closing tags
│   └── data.php         # placeholder data + status logic (products, machines, supplies)
└── assets/
    ├── css/style.css
    ├── js/main.js       # shared (nav)
    ├── js/login.js
    ├── js/dashboard.js
    ├── js/table-filter.js  # search/filter used by the table pages
    └── img/             # put the DermAve logo here
```

## Running locally

1. Install [XAMPP](https://www.apachefriends.org/) (or any PHP + Apache stack).
2. Put this folder inside `htdocs` (XAMPP) so it lives at `htdocs/dermave-ims`.
3. Start Apache, then open `http://localhost/dermave-ims/`.
4. Demo login (placeholder only): `admin` / `admin123`

## Pages

- [x] Login
- [x] Dashboard
- [x] Products
- [x] Machines
- [x] Supplies
- [x] Directory
- [ ] Barcode

## Team workflow

- Pull before you start working.
- Commit small, with clear messages.
- Work on a branch for each page/feature and open a pull request into `main`.

## Database (Directory)

1. Start MySQL (XAMPP) and open phpMyAdmin.
2. Import or run the script:
   `sql/directory_schema.sql`
3. It creates the database `dermave_ims`, the table `directory_items`, and seeds
   5 machines + 5 products + 5 supplies from the DermAve Combined Item List.
4. Update credentials in `includes/config.php` if needed (`DB_HOST`, `DB_USER`, `DB_PASS`).
5. Once the DB is reachable, `directory.php`, `products.php`, `machines.php` and
   `supplies.php` automatically use it (with a sample-data fallback otherwise).

The Directory page has:
- Search
- Add / Edit / Archive buttons (Edit & Archive activate when row(s) are selected)
- Clickable column headers that open sort + filter menus (Type, Name, Category, Qty, Date, Status)

### Directory: Add / Edit / Archive

- **Add** and **Edit** open a form; the fields shown depend on the type (product / machine / supply).
- **Archive** sets `status = 'archived'` (nothing is deleted). Archived rows stay visible, dimmed, on the
  Directory page and are hidden from Products / Machines / Supplies. Selecting archived rows turns the
  button into **Restore**.
- If the page shows "Not connected to the database", start MySQL in XAMPP and check `DB_HOST`, `DB_PORT`,
  `DB_NAME`, `DB_USER`, `DB_PASS` in `includes/config.php`.
