# Elite Padel League

## Flujo de trabajo (importante)

1. **Desarrollás y probás en local** (`http://localhost/elitepadelleague/`).
2. **Nada se sube** a GitHub ni al VPS hasta que vos digas que está aprobado.
3. **Cuando apruebes**, publicá todo junto (GitHub + VPS):

```powershell
.\scripts\publicar.ps1 -Aprobado -Mensaje "qué cambiaste y por qué"
```

O pedile al asistente en Cursor: **«Publica todo: [descripción]»**

**No** hagas `git push` ni despliegue por tu cuenta antes de que el usuario apruebe.

## Si el webhook del VPS falla

El jugador/admin puede hacer en la consola Vultr: `cd` a la carpeta del proyecto y `git pull`.

## Rama

Trabajar en **`main`**. No commitear `.env` ni `.claude/`.
