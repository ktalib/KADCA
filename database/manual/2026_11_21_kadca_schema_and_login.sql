/* ==========================================================================================
   KADCA: own schema + own login inside the shared KLAES database
   ------------------------------------------------------------------------------------------
   Server   : 10.50.1.1
   Run as   : a sysadmin (klaesDb cannot - it is db_owner only, and logins are server-level)
   Target   : DEV FIRST - [klas_dev_20261007]. Production [klas] only after the dev checks
              (Part D) pass and the KADCA app has been tried against dev. To do production,
              change only the USE line in Part B and re-run Parts B-D (Part A is once per server).
   Re-run   : safe; every step checks before it creates.

   What it gives the KADCA app (login kadca_app):
     - its own schema [kadca], which it owns: it can create/alter/drop its OWN tables there.
       Laravel creates them unqualified (kadca_users, kadca_shadow_files...), and the user's
       DEFAULT_SCHEMA puts them in [kadca].
     - read access to every KLAES table in [dbo] (unqualified names still find them).
     - NO ALTER on [dbo]: it cannot DROP, ALTER or TRUNCATE any KLAES table. A db:wipe /
       migrate:fresh run as this login fails with "permission denied" on the KLAES tables.
     - NO DELETE on [dbo]: KLAES flags, it never deletes.
     - writes to shared KLAES tables only where Part C grants them, table by table.

   Why the schema is owned by kadca_app and not dbo: if dbo owned it, anything kadca_app
   created there would be dbo-owned, and ownership chaining would let it reach dbo tables
   unchecked. With kadca_app as owner the chain breaks and every KLAES access is checked.
   ========================================================================================== */


/* ---------- Part A: the login (once per server) ------------------------------------------ */
/* The password must pass the Windows password policy (CHECK_POLICY = ON), or nothing below runs:
     - at least 8 characters (use 16 or more),
     - characters from at least 3 of: UPPER case, lower case, digits, symbols,
     - must not contain the login name (kadca_app).
   Type it here, run, then delete it from this file again. It goes in the KADCA .env only. */
USE [master];
GO
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'kadca_app')
    CREATE LOGIN [kadca_app]
        WITH PASSWORD = N'DDI783673PASSWORD',
             DEFAULT_DATABASE = [klas_dev_20261007],
             CHECK_POLICY = ON,
             CHECK_EXPIRATION = OFF;                          -- an app account; an expiry would take KADCA down
GO
-- Stop here if the login is not there (e.g. password rejected): SET NOEXEC ON skips every later batch.
IF SUSER_ID(N'kadca_app') IS NULL
BEGIN
    RAISERROR(N'STOPPED: login kadca_app was not created (see the error above). Nothing else was run. Fix it and run the whole script again.', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------- Part B: user, schema, permissions (once per database) ----------------------- */
USE [klas_dev_20261007];   -- <<< DEV. For production change to [klas].
GO
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'kadca_app')
    CREATE USER [kadca_app] FOR LOGIN [kadca_app];
GO
IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = N'kadca')
    EXEC (N'CREATE SCHEMA [kadca] AUTHORIZATION [kadca_app]');
GO
ALTER USER [kadca_app] WITH DEFAULT_SCHEMA = [kadca];
GO

-- Own tables (needed by Laravel migrations: tables, indexes, the migrations table itself).
GRANT CREATE TABLE TO [kadca_app];
GRANT CREATE VIEW  TO [kadca_app];

-- KLAES tables: read everything, change nothing structural, delete nothing.
GRANT SELECT ON SCHEMA::[dbo] TO [kadca_app];
DENY  ALTER  ON SCHEMA::[dbo] TO [kadca_app];   -- blocks DROP / ALTER / TRUNCATE of KLAES tables
DENY  DELETE ON SCHEMA::[dbo] TO [kadca_app];   -- a DENY here also overrides any later per-table GRANT DELETE
GO


/* ---------- Part C: shared KLAES tables KADCA may write (fill in once agreed) ------------
   One line per table the user approves. Rows written must set [source] = 'KADCA...'.
   Example (do NOT uncomment until approved):
   GRANT INSERT, UPDATE ON [dbo].[billing] TO [kadca_app];
   ----------------------------------------------------------------------------------------- */


/* ---------- Part D: checks - every row must say OK ------------------------------------- */
EXECUTE AS USER = N'kadca_app';

SELECT check_name, CASE WHEN actual = expected THEN 'OK' ELSE '** FAIL **' END AS result, actual, expected
FROM (VALUES
    ('default schema is kadca',              CASE WHEN SCHEMA_NAME() = N'kadca' THEN 1 ELSE 0 END, 1),
    ('can read KLAES  (dbo.fileNumber)',     HAS_PERMS_BY_NAME(N'dbo.fileNumber', N'OBJECT', N'SELECT'), 1),
    ('cannot alter    (dbo.fileNumber)',     HAS_PERMS_BY_NAME(N'dbo.fileNumber', N'OBJECT', N'ALTER'),  0),
    ('cannot delete   (dbo.fileNumber)',     HAS_PERMS_BY_NAME(N'dbo.fileNumber', N'OBJECT', N'DELETE'), 0),
    ('cannot alter    (dbo.billing)',        HAS_PERMS_BY_NAME(N'dbo.billing',    N'OBJECT', N'ALTER'),  0),
    ('cannot alter    (dbo.users)',          HAS_PERMS_BY_NAME(N'dbo.users',      N'OBJECT', N'ALTER'),  0),
    ('cannot alter the dbo schema',          HAS_PERMS_BY_NAME(N'dbo',   N'SCHEMA', N'ALTER'), 0),
    ('cannot alter the database',            HAS_PERMS_BY_NAME(DB_NAME(), N'DATABASE', N'ALTER'), 0),
    ('owns its schema (kadca)',              HAS_PERMS_BY_NAME(N'kadca', N'SCHEMA', N'CONTROL'), 1),
    ('can create tables',                    HAS_PERMS_BY_NAME(DB_NAME(), N'DATABASE', N'CREATE TABLE'), 1),
    ('no access to the other app (kamma)',   CASE WHEN SCHEMA_ID(N'kamma') IS NULL THEN 0
                                                  ELSE HAS_PERMS_BY_NAME(N'kamma', N'SCHEMA', N'ALTER') END, 0)
) AS c(check_name, actual, expected);

-- Real create/drop of a probe table, unqualified, the way Laravel will do it.
CREATE TABLE kadca__permission_probe (id INT);
SELECT 'probe table landed in' AS check_name, SCHEMA_NAME(schema_id) AS [schema]   -- expect: kadca
FROM sys.tables WHERE name = N'kadca__permission_probe';
DROP TABLE kadca__permission_probe;

REVERT;
GO
SET NOEXEC OFF;   -- back to normal for this window (it is ON only if the script stopped early)
GO


/* ---------- Undo (only if this has to be taken back out; kadca tables must be moved or dropped first)
USE [klas_dev_20261007];
DROP USER   [kadca_app];          -- fails while it still owns [kadca]: ALTER AUTHORIZATION ON SCHEMA::[kadca] TO [dbo] first
DROP SCHEMA [kadca];              -- fails while the schema still holds tables
USE [master];
DROP LOGIN  [kadca_app];          -- only once no database still maps it
---------------------------------------------------------------------------------------------- */
