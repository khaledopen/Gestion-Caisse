@echo off
cd /d "%~dp0"
echo Clair demarre sur http://127.0.0.1:8000
echo Fermez cette fenetre ou utilisez Ctrl+C pour arreter le serveur.
call "%~dp0php.cmd" artisan serve --host=127.0.0.1 --port=8000
