@echo off
REM Script para ejecutar el CRON manualmente en Windows/MAMP
REM Uso: Doble clic en este archivo o ejecutar desde CMD
REM NOTA: Requiere que Apache esté corriendo para acceder vía HTTP

echo ========================================
echo    CRON: Contratos Temporales - SGRH
echo ========================================
echo.

REM Opción 1: Ejecutar vía HTTP (recomendado para MAMP)
echo Ejecutando CRON via HTTP...
curl http://localhost/sgrh/cron_contratos_temporales.php

echo.
echo ========================================
echo Ejecución completada
echo Ver log en: storage\logs\
echo ========================================
echo.

pause
