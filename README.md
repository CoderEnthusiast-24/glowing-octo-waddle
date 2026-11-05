# DermAve IMS

Web-based Inventory Management System with Barcode Scanner for **DermAve Medical Aesthetic & Wellness Clinic**.

A group project for a dermatology study.

## Tech stack

- HTML
- CSS
- JavaScript
- PHP
- MySQL (XAMPP / phpMyAdmin)

## Theme

Based on the DermAve logo: black, gold (`#ffe066`) and soft white.

## Project structure

```
dermave-ims/
├── index.php            # redirects to login
├── login.php            # login page
├── forgot.php           # forgot password page (administrators only)
├── logout.php           # ends the session
├── dashboard.php        # dashboard page
├── directory.php        # unified directory (all items, Add / Edit / Archive)
├── directory_api.php    # saves Add / Edit / Archive / Restore to MySQL (JSON)
├── products.php         # products page (item, batch, expiry, qty, status)
├── machines.php         # machines page (maintenance dates + status)
├── supplies.php         # supplies page (low stock, out of stock, stock list)
├── barcode.php          # barcode page (in progress)
├── stock.php            # stock in / stock out page + recent movements
├── stock_api.php        # saves a stock movement (JSON)
├── users.php            # users page (administrators only, reset passwords)
├── includes/
│   ├── config.php       # app + database settings
│   ├── db.php           # the one database connection used by every page
│   ├── session.php      # starts the session, finds the logged-in user
│   ├── auth.php         # protects pages that need login
│   ├── users.php        # password checking + saving (used by forgot.php and users.php)
│   ├── stock.php        # stock_move(): the one place that changes quantity + logs it
│   ├── header.php       # shared top bar + navigation
│   ├── footer.php       # shared scripts + closing tags
│   └── data.php         # placeholder data + status logic (products, machines, supplies)
├── sql/
│   ├── directory_schema.sql   # creates the database and the directory_items table
│   ├── users_schema.sql       # creates the users table
│   └── stock_movements.sql    # creates the stock_movements log table
└── assets/
    ├── css/style.css
    ├── css/barcode.css
    ├── js/main.js       # shared (nav)
    ├── js/login.js      # show / hide password button
    ├── js/dashboard.js
    ├── js/directory.js
    ├── js/table-filter.js  # search/filter used by the table pages
    ├── js/stock.js      # stock page (In / Out form)
    ├── css/stock.css
    └── img/             # put the DermAve logo here
```

## Running locally

1. Install [XAMPP](https://www.apachefriends.org/) (or any PHP + Apache stack).
2. Put this folder inside `htdocs` (XAMPP) so it lives at `htdocs/dermave-ims`.
3. Start **Apache** and **MySQL** in the XAMPP control panel.
4. Set up the database (see below).
5. Open `http://localhost/dermave-ims/`.

## Database setup

1. Open phpMyAdmin (`http://localhost/phpmyadmin`).
2. Import or run `sql/directory_schema.sql`.
   It creates the database `dermave_ims`, the table `directory_items`, and fills it with
   sample machines, products and supplies from the DermAve Combined Item List.
3. Import or run `sql/users_schema.sql`.
   It creates the `users` table (the login accounts). The table starts empty, so add
   your accounts in phpMyAdmin: `dermave_ims` > `users` > **Insert**.
   - `role` is `admin` or `staff`
   - `status` should be `active`
4. Import or run `sql/stock_movements.sql` (needs `directory_items` to exist first).
   It creates the `stock_movements` log used by the Stock page.
5. If your XAMPP settings are different, update `DB_HOST`, `DB_PORT`, `DB_USER` and
   `DB_PASS` in `includes/config.php`.

Once the database is reachable, `directory.php`, `products.php`, `machines.php` and
`supplies.php` use it automatically (with a sample-data fallback otherwise).

## Login

- Accounts live in the `users` table.
- **Passwords are stored as plain text** in the `password` column. This is only
  acceptable for a school project, not for a real system.
- Roles:
  - **admin** can see the Users page and can use "Forgot?".
  - **staff** can use every other page.
- A `disabled` account cannot log in.

### How login works

1. You type your username and password on `login.php`.
2. The page looks for that username in the `users` table and compares the password.
3. If both match and the account is `active`, your user id is saved in the PHP session
   and you are sent to the dashboard.
4. Every page after that checks the session first. If nobody is logged in, it sends you
   back to `login.php`.
5. **Logout** clears the session.

### Forgot password

1. Click **Forgot?** on the login page.
2. Type your username and press **Continue**.
   - If the username is an active **admin**, the page asks for a new password and a confirmation.
   - If not (staff, disabled, or unknown), it says to contact the system administrator.
3. Type the new password twice (at least 8 characters) and press **Change password**.
   The `users` table is updated and you are sent back to the login page.

### Users page (admins only)

An admin can open **Users** in the menu, click **Reset password** next to any account,
type a new password twice and save. This is also how a staff member gets a new password.

## Pages

- [x] Login
- [x] Forgot password
- [x] Dashboard
- [x] Products
- [x] Machines
- [x] Supplies
- [x] Directory
- [x] Users
- [x] Stock (in / out)
- [ ] Barcode

## Directory

The Directory page has:
- Search
- Add / Edit / Archive buttons (Edit & Archive activate when row(s) are selected)
- Clickable column headers that open sort + filter menus (Name, Category, Qty, Date, Status)

### Add / Edit / Archive

- **Add** and **Edit** open a form; the fields shown depend on the type (product / machine / supply).
- **Archive** sets `status = 'archived'` (nothing is deleted). Archived rows stay visible, dimmed, on the
  Directory page and are hidden from Products / Machines / Supplies. Selecting archived rows turns the
  button into **Restore**.
- If the page shows "Not connected to the database", start MySQL in XAMPP and check `DB_HOST`, `DB_PORT`,
  `DB_NAME`, `DB_USER`, `DB_PASS` in `includes/config.php`.

## Team workflow

- Pull before you start working.
- Commit small, with clear messages.
- Work on a branch for each page/feature and open a pull request into `main`.
## Stock in / out

- The **Stock** page lists products and supplies (machines are excluded). Click **In** or **Out** on a row,
  enter a quantity and a reason, and save.
- All changes go through `stock_move()` in `includes/stock.php`, which updates `directory_items.quantity`
  and writes one row to `stock_movements` (type, quantity, balance after, reason, user) in a single transaction.
- Stock out is refused if it would go below zero, and archived items cannot be moved.
- The Barcode page and future treatment deductions should call `stock_move()` too, so quantity changes stay in one place.
