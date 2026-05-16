# Elite Padel League — Claude Code

## Publicar (GitHub + VPS)

**No publiques nada** hasta que el usuario lo pida.

Cuando diga **"publica"**, **"sube"**, **"sube los cambios"**, **"despliega"** o similar:

1. `git status` → resumen breve de qué archivos van.
2. Ejecutar en la raíz del proyecto:

```powershell
.\scripts\publicar.ps1 -Mensaje "descripción del cambio" -Desplegar
```

3. Confirmar: commit, push a `main` y respuesta del webhook del VPS.

**Solo GitHub (sin VPS)** — solo si el usuario dice explícitamente *"solo github"* o *"sin desplegar"*:

```powershell
.\scripts\publicar.ps1 -Mensaje "descripción del cambio"
```

### Reglas

- Rama **`main`**. No usar `.claude/worktrees/` para el código final.
- No commitear `.env`, secretos ni `.claude/`.
- Commit en español, una frase con el **por qué**.
- `DEPLOY_TOKEN` en `.env` local (necesario para `-Desplegar`).

## Desarrollo

- PHP + MySQL. Probar en `http://localhost/elitepadelleague/` antes de publicar.
- Repo: `https://github.com/pabloromeroduarte-sys/epl_app.git`
