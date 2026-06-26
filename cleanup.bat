@echo off
echo ========================================
echo   LIMPEZA DO PROJETO - Farmacia Gingongo
echo ========================================
echo.
echoRemovendo arquivos mortos e legacy...
echo.

REM === PUBLIC/_LEGACY (27 arquivos) ===
echo [1/6] Removendo public/_legacy/...
rmdir /s /q "public\_legacy" 2>nul
echo       OK

REM === CSS duplicados mortos ===
echo [2/6] Removendo CSS/JS mortos...
del /q "public\css\style.css" 2>nul
del /q "public\css\style-new.css" 2>nul
del /q "public\js\script.js" 2>nul
del /q "public\sidebar.php" 2>nul
echo       OK

REM === Database duplicates ===
echo [3/6] Removendo Database duplicados...
del /q "src\Database\Database_SQLite.php" 2>nul
del /q "src\Database\Database_MySQL.php" 2>nul
echo       OK

REM === Root test/migration PHP files ===
echo [4/6] Removendo arquivos de teste/migração da raiz...
del /q "test_login.php" 2>nul
del /q "test_db.php" 2>nul
del /q "test_system.php" 2>nul
del /q "EXECUTE_AGORA.php" 2>nul
del /q "execute_now.php" 2>nul
del /q "fix_uniformes_table.php" 2>nul
del /q "run_phase3_migration.php" 2>nul
del /q "check_database.php" 2>nul
echo       OK

REM === Root batch files (keep install_pdf.bat and iniciar_sistema.bat) ===
echo [5/6] Removendo batch files desnecessários...
del /q "RUN.bat" 2>nul
del /q "run_migration.bat" 2>nul
del /q "run_execute.bat" 2>nul
del /q "run_migrations.bat" 2>nul
echo       OK

REM === Root txt documentation (keep README.md and key docs) ===
echo [6/6] Removendo documentação desnecessária...
del /q "00-START-HERE.txt" 2>nul
del /q "START-HERE.txt" 2>nul
del /q "WELCOME.txt" 2>nul
del /q "CONCLUSAO.txt" 2>nul
del /q "COMPARACAO-PLANO-VS-REALIDADE.txt" 2>nul
del /q "COMO-FAZER-LOGIN.txt" 2>nul
del /q "RESOLUCAO-COMPLETA.txt" 2>nul
del /q "STATUS-FINAL-PHASE3.txt" 2>nul
del /q "IMPLEMENTACAO-REAL.txt" 2>nul
del /q "PHASE3-IMPLEMENTATION-SUMMARY.txt" 2>nul
del /q "FINAL-SUMMARY.txt" 2>nul
del /q "PHASE2-COMPLETE.txt" 2>nul
del /q "INDEX-FASE2.txt" 2>nul
del /q "STATUS-FINAL-FASE2.txt" 2>nul
del /q "QUICK-START.txt" 2>nul
del /q "FASE2-ADMIN-CONCLUSAO-FINAL.txt" 2>nul
del /q "FASE2-CONCLUIDO.txt" 2>nul
del /q "README-RAPIDO.txt" 2>nul
echo       OK

REM === API migration files ===
echo Removendo API de migração...
del /q "public\api\cleanup-migration.php" 2>nul
del /q "public\api\verify-phase2.php" 2>nul
del /q "public\api\migrate-phase2.php" 2>nul
echo       OK

echo.
echo ========================================
echo   LIMPEZA CONCLUIDA!
echo ========================================
echo.
echo Arquivos removidos:
echo   - 27 arquivos legacy
echo   - 3 CSS/JS mortos
echo   - 2 Database duplicados
echo   - 8 arquivos teste/migracao
echo   - 4 batch files
echo   - 18 arquivos txt
echo   - 3 API migracao
echo   - 1 sidebar duplicada
echo.
echo Total: ~66 arquivos removidos
echo.
pause
