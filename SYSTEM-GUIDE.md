# Petroleum Management System: Navigation and Training Script

## Purpose

Use this guide to introduce the system, explain each role, and walk staff through a normal workday. The menus and available pages depend on the signed-in user's role.

## Presenter script

> Welcome to the Petroleum Management System. It brings branch operations, fuel sales, expenses, inventory, invoices, payments, and reporting into one place.
>
> To begin, open the system's login page, enter your assigned username and password, and sign in. The system takes you to the dashboard for your role. Use the sidebar to move between pages. The Dashboard is the best place to start: it summarizes activity and helps you spot items that need attention.
>
> Before recording a transaction, confirm that you are working in the correct branch and that the date, fuel, quantity, price, and payment details are accurate. Save the transaction and review the confirmation or receipt. If you notice a mistake, follow the approved correction process instead of creating a second transaction to hide it.
>
> Each role has a different responsibility. Attendants record sales and provide receipts. Managers supervise branch operations, review sales and expenses, and monitor inventory and equipment. Accountants work with invoices, payments, and financial reports. Administrators manage users, branches, system-wide operations, and settings.
>
> At the end of a shift or reporting period, review the applicable history and reports, and raise any discrepancies with your supervisor. Sign out when you finish, especially on a shared device.
>
> Accurate and timely records help the organization understand what was sold, what stock remains, where money was received or spent, and which actions need follow-up. The system supports accountability and better decisions; it does not replace required approvals, physical stock checks, or sound financial controls.

## Sign in and navigate

1. Open the login page. In the local XAMPP installation, it is:
   `http://127.0.0.1/Petrolem%20Managemennt/public/login.php`
2. Enter the username and password issued to you. Do not use another person's account.
3. The application opens the dashboard associated with your role.
4. Choose a section from the sidebar. On a narrow screen, use the menu button to open the navigation.
5. Use the page's filters and date ranges to focus on the right branch and period. Check the selected values before relying on a report.
6. Sign out using the **Logout** or **Sign out** menu item when finished.

## Roles and where to go

### Attendant

Attendants handle point-of-sale work for their assigned branch.

- **Dashboard:** Check the shift overview and available operational information.
- **Fuel Sales:** Record a fuel sale, entering the requested fuel, quantity, and payment details accurately.
- **Receipts:** Review or provide receipts for recorded sales.
- **Sales History:** Find and review previous sales.
- **Daily Report / Monthly Report / Performance:** Review personal sales activity and performance information.
- **Fuel Calculator / Unit Converter / Price List:** Use these tools to check quantities, conversions, and displayed prices. Confirm the current approved price before completing a sale.

**Typical flow:** Open the dashboard, go to Fuel Sales, verify the branch and sale details, submit the sale, provide the receipt, and use Sales History or a report to review the recorded transaction.

### Manager

Managers oversee branch operations and follow up on sales, expenses, inventory, and equipment.

- **Dashboard:** Review the branch overview.
- **Sales / New Sale:** Review sales or enter a new sale where the workflow permits.
- **Expenses / Add Expense:** Record expenses and follow the approval process.
- **Fuel Inventory:** Use the dashboard's inventory section to review fuel stock.
- **Equipment Alerts:** Review equipment issues that need attention.
- **Reports:** Review branch performance for the selected period.

**Typical flow:** Start at the dashboard, review inventory and alerts, check sales and expenses, record or follow up on operational items, and use Reports to review the branch's results.

### Accountant

Accountants focus on financial records and reporting.

- **Dashboard:** Review financial activity and available summaries.
- **Invoices:** Review and manage invoice records.
- **Payments:** Review and manage payment records.
- **Financial Reports:** Choose a report type and date range to review financial summaries, profit and loss, expense analysis, or sales trends.

**Typical flow:** Review the dashboard, reconcile invoice and payment information against supporting records, then use Financial Reports for the required reporting period. Follow the organization's approval and reconciliation controls.

### Administrator

Administrators maintain the organization-wide setup and access.

- **Dashboard / Analytics / Reports:** Monitor system-wide activity and results.
- **Users / Attendance / Audit Logs:** Manage staff access and attendance records, and review activity records.
- **Branches:** Maintain branch information and review branch performance and settings.
- **Inventory:** Manage or review tanks, pumps, and related inventory information.
- **Sales / Expenses / Transactions / Invoices:** Review operational and financial records across the system.
- **System Settings:** Maintain general, email, SMS, payment, and backup settings as authorized.
- **API Management / System Logs / Notifications:** Review technical integrations, system events, and alerts.
- **Import/Export / Maintenance / Help & Documentation:** Use administrative utilities carefully and only when authorized.

**Typical flow:** Confirm branch and user setup, review alerts and system activity, maintain operational records, and use reports and logs to support oversight. Backups, imports, deletion, and configuration changes should be handled cautiously and according to internal procedures.

## Why use the system

- **Accurate operations:** A consistent record of sales, stock, expenses, invoices, and payments helps reduce omissions and conflicting records.
- **Inventory awareness:** Current stock and equipment alerts help teams identify shortages or operational problems sooner.
- **Financial visibility:** Sales, expenses, invoices, and payments can be reviewed over a selected time period to support reconciliation and planning.
- **Accountability:** Role-based access and recorded activity help clarify who is responsible for a task and support follow-up.
- **Branch oversight:** Managers and administrators can review performance by branch and identify where attention is needed.
- **Better decisions:** Reliable reports support purchasing, staffing, pricing, and operational decisions.
- **Customer service:** Correct sale details and receipts make transactions easier to verify and resolve.

## Good operating practices

- Use only your own assigned account. Do not share passwords or leave an authenticated session open on a shared computer.
- Confirm the branch, date, fuel type, quantity, amount, and payment details before saving a transaction.
- Record transactions promptly and retain supporting documents required by company policy.
- Review receipts and history after a sale; report discrepancies rather than making an undocumented adjustment.
- Use the correct date range and branch filters when reviewing reports.
- Managers should follow the required expense approval and stock-check procedures.
- Accountants should reconcile system records with the relevant source documents and approvals.
- Administrators should limit account, settings, import, backup, and maintenance actions to authorized personnel.
- Sign out at the end of the session. Contact the administrator if access or data appears incorrect.

## Short role-based handoff

> Attendants record sales and provide receipts. Managers oversee branch operations, expenses, inventory, and equipment. Accountants manage invoices, payments, and financial reporting. Administrators maintain users, branches, system settings, and organization-wide oversight. Everyone should record information accurately, check their work, follow approval procedures, protect their login, and sign out when done.
