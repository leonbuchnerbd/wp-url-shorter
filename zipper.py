import os
import zipfile

def zip_folder(folder_path, output_zip):
    with zipfile.ZipFile(output_zip, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(folder_path):
            # Verzeichnis ".git" ignorieren
            if '.git' in dirs:
                dirs.remove('.git')
            for file in files:
                # .zip-Dateien ignorieren
                if file.endswith('.zip'):
                    continue
                if file.endswith('zipper.py'):
                    continue
                file_path = os.path.join(root, file)
                # Erstelle den relativen Pfad für die Archivstruktur
                arcname = os.path.relpath(file_path, folder_path)
                zipf.write(file_path, arcname)

if __name__ == "__main__":
    current_folder = os.path.abspath(".")
    output_zip = os.path.join(current_folder, "url-shortner.zip")
    zip_folder(current_folder, output_zip)
    print("Ordner wurde erfolgreich in", output_zip, "gezippt.")
