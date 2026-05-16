# Elite Padel League — cómo publicar

## Mientras desarrollás

- Probá en local: `http://localhost/elitepadelleague/`
- **No subas nada** hasta que el usuario diga que está listo.

## Cuando el usuario diga que publique

Ejemplos: *"publica todo"*, *"queda arriba"*, *"haz el pull"*, *"está aprobado"*.

### 1) Subir a GitHub

```powershell
cd C:\xampp\htdocs\elitepadelleague
.\scripts\publicar.ps1 -Mensaje "descripción breve del cambio"
```

(Sin `-Aprobado` si solo pide GitHub. Con `-Aprobado` si también toca el servidor.)

### 2) Actualizar el sitio en Vultr

Decile al usuario que abra **my.vultr.com** → su servidor → **View Console** y pegue **solo esta línea**:

```bash
git -C /home/elitepadel/htdocs/padel.207.246.68.77.nip.io pull origin main
```

Si sale error de permisos, que pegue antes:

```bash
sudo chown -R elitepadel:elitepadel /home/elitepadel/htdocs/padel.207.246.68.77.nip.io
```

y vuelva a hacer el `git pull`.

### 3) Listo

El sitio queda actualizado. No des explicaciones técnicas largas: pasos copiar/pegar.

## Reglas

- Rama `main`. No commitear `.env`.
- Mensaje de commit en español, una frase.
