# Windows/PowerShell Setup Guide

## Quick Start for Windows Users

### Prerequisites
- **PHP** installed and in your PATH
- **Composer** installed
- **Make** utility (comes with Git for Windows, or install via Chocolatey: `choco install make`)

### Common Issues and Solutions

#### 1. Migration Error: "table already exists" or "syntax error"

If you see errors like `table category already exists` or `near "CONSTRAINT": syntax error`:

**Solution:** The migrations have been fixed to work with both SQLite and PostgreSQL. Reset the database:
```powershell
# Delete the SQLite database file if it exists
if (Test-Path "var\data_dev.db") { Remove-Item "var\data_dev.db" }

# Then reset and migrate
make db-reset
```

Or simply:
```powershell
make db-reset
```

The `db-reset` command will now properly handle SQLite database deletion.

#### 2. Admin User Creation Interrupted

The admin user creation is interactive and requires input. If it gets interrupted:

**Solution:** Run it separately:
```powershell
make admin-user
```

Then follow the prompts to enter email and password.

#### 3. Using Make in PowerShell

If you're using native PowerShell (not Git Bash), you have two options:

**Option A: Use Git Bash** (Recommended)
- Open Git Bash instead of PowerShell
- All commands work as expected

**Option B: Use PowerShell directly**
- Most commands work, but some may need adjustment
- Color codes may not display correctly (this is cosmetic)

### Essential Commands

```powershell
# Complete setup (first time)
make setup

# If setup fails due to existing tables
make db-reset
make admin-user

# Check migration status
make db-migrate-status

# Start development server
make server-start

# View all available commands
make help
```

### Troubleshooting

#### PHP Warning: Module "ftp" is already loaded
This is a harmless warning about PHP configuration. It doesn't affect functionality.

#### Make command not found
Install make via:
- **Chocolatey:** `choco install make`
- **Git for Windows:** Includes make in Git Bash
- **WSL:** Use `wsl make` from PowerShell

#### Database connection issues
1. Check your `.env` file for database configuration
2. Ensure your database server is running
3. Verify credentials are correct

### Next Steps

After successful setup:
1. Run `make server-start` to start the development server
2. Visit `http://localhost:8000` in your browser
3. Access admin panel at `http://localhost:8000/admin`

For more help, run `make help` to see all available commands.

