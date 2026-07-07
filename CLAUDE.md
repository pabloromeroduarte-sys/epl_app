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

### 2) Actualizar el sitio (DigitalOcean)

El paso 1 con `-Aprobado` ya despliega solo (webhook → `git pull` + reset de OPcache en el servidor). No hay que hacer nada más.

Si el webhook falla, el manual es por SSH:

```bash
ssh root@165.227.109.215
git -C /var/www/elitepadelleague pull origin main
```

(El servidor viejo de Vultr `207.246.68.77` quedó como respaldo; no se toca.)

### 3) Listo

El sitio queda actualizado. No des explicaciones técnicas largas: pasos copiar/pegar.

## Reglas

- Rama `main`. No commitear `.env`.
- Mensaje de commit en español, una frase.
