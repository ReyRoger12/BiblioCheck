# Diagrama de Clases BiblioCheck

Este directorio contiene el diagrama de clases en formato PlantUML para el sistema BiblioCheck.

## Archivos

- `bibliocheck.sql`: Script SQL con la estructura de la base de datos
- `class-diagram.plantuml`: Diagrama de clases en formato PlantUML (código fuente)
- `BiblioCheck - Diagrama de Clases.png`: Imagen del diagrama generado

## Visualización del Diagrama

### Vista Previa Rápida
Puedes ver directamente la imagen generada: `BiblioCheck - Diagrama de Clases.png`

### Opción 1: Extensión de VS Code
1. Instala la extensión "PlantUML" en Visual Studio Code
2. Abre el archivo `class-diagram.plantuml`
3. Presiona `Alt+D` para previsualizar el diagrama

### Opción 2: PlantUML Online
1. Visita [PlantUML Online Server](http://www.plantuml.com/plantuml/uml/)
2. Copia el contenido del archivo `class-diagram.plantuml`
3. Pégalo en el editor online para visualizarlo

### Opción 3: Línea de Comandos
Instala PlantUML localmente:
```bash
# En Ubuntu/Debian
sudo apt-get install plantuml

# Genera la imagen
plantuml class-diagram.plantuml
```

Esto generará un archivo `BiblioCheck - Diagrama de Clases.png` con el diagrama visual (el nombre se toma de la directiva @startuml en el archivo).

## Estructura de la Base de Datos

El diagrama representa las siguientes entidades principales:

### Tablas

1. **alumnos**: Información de estudiantes del sistema
   - Datos personales (nombre, teléfono, correo)
   - Datos académicos (número de control, semestre, carrera)
   - Autenticación (contraseña, QR semanal)
   - Horarios y puestos asignados

2. **puestos**: Puestos de trabajo o áreas disponibles en la biblioteca
   - Identificador y nombre del puesto

3. **alumnos_puestos**: Tabla intermedia para la relación muchos a muchos
   - Relaciona alumnos con sus puestos asignados

4. **asistencia**: Registro de entrada y salida de alumnos
   - Fecha y hora del registro
   - Tipo de registro (entrada/salida)
   - Referencias a alumno y puesto

5. **usuarios**: Usuarios administrativos del sistema
   - Administradores y docentes
   - Credenciales de acceso
   - Estado de la cuenta

6. **documentos**: Documentos subidos por usuarios
   - Información del archivo
   - Ruta de almacenamiento
   - Relación con el usuario que lo subió

### Relaciones

- **alumnos ↔ puestos**: Relación muchos a muchos a través de `alumnos_puestos`
  - Un alumno puede tener múltiples puestos
  - Un puesto puede ser asignado a múltiples alumnos

- **alumnos → asistencia**: Uno a muchos
  - Un alumno puede tener múltiples registros de asistencia

- **puestos → asistencia**: Uno a muchos
  - Un puesto puede tener múltiples registros de asistencia

- **usuarios → documentos**: Uno a muchos
  - Un usuario puede subir múltiples documentos

## Notas Técnicas

El diagrama documenta fielmente la estructura actual de la base de datos. Se han identificado algunas inconsistencias en el esquema original que podrían requerir atención en futuras revisiones:

- **Inconsistencia de tipos**: El campo `id_alumno` es `varchar(20)` en la tabla `alumnos` pero `varchar(6)` en `alumnos_puestos`
- **Falta de claves foráneas explícitas**: La tabla `alumnos_puestos` no define explícitamente sus claves foráneas en el SQL
- **Posible desnormalización**: El campo `puesto` en la tabla `alumnos` podría ser redundante dado que existe la relación muchos a muchos con `puestos`

## Actualización del Diagrama

Si realizas cambios en la estructura de la base de datos (`bibliocheck.sql`), asegúrate de actualizar también el diagrama PlantUML (`class-diagram.plantuml`) para mantener la documentación sincronizada.
