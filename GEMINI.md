# Reglas de Flujo de Trabajo y Colaboración - KORTZEN

Este proyecto se desarrolla de forma simultánea desde dos máquinas/entornos:
- **Entorno Linux** (Antigravity / Linux)
- **Entorno Windows** (Entorno de desarrollo en Windows)

---

## Directrices Obligatorias para Asistentes y Desarrolladores

1. **Sincronización frecuente (`git pull` previo):**
   - Antes de iniciar cualquier tarea o modificar archivos de código, verificar y ejecutar `git pull origin main` para asegurar que se cuenta con los últimos cambios subidos desde la otra máquina.

2. **Uso de ramas y commits atómicos:**
   - Para nuevas funcionalidades o cambios sustanciales, trabajar en ramas dedicadas (`feature/...` o `fix/...`).
   - Crear commits pequeños, atómicos y descriptivos que faciliten la integración continua y minimicen conflictos de merge.

3. **Control de saltos de línea (CRLF / LF):**
   - En Linux: debe mantenerse `git config core.autocrlf input` (archivos guardados estrictamente en formato `LF`).
   - En Windows: debe mantenerse `git config core.autocrlf true`.

4. **Verificación continua de estado (`git status`):**
   - Comprobar `git status` antes de proponer o aplicar cambios importantes para garantizar que no existan modificaciones no rastreadas o inconsistencias en el árbol de trabajo.
