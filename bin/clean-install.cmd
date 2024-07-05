@echo off

:: Activate the virtual environment
call D:\xampp\htdocs\venv\Scripts\activate

:: Clear Python cache
for /r %%i in (__pycache__) do (
    if exist "%%i" (
        rd /s /q "%%i"
    )
)

:: Clear pip cache
echo Clearing pip cache...
python -m pip cache purge

:: Freeze current packages to uninstall.txt
echo Freezing current packages to uninstall.txt...
python -m pip freeze > uninstall.txt

:: Uninstall all packages listed in uninstall.txt
echo Uninstalling all packages...
python -m pip uninstall -r uninstall.txt -y

:: Remove the uninstall.txt file
echo Removing uninstall.txt...
del uninstall.txt

:: Reinstall packages from requirements.txt
echo Reinstalling packages from requirements.txt...
python requirements_install.py

:: Deactivate the virtual environment
deactivate

echo Done!
