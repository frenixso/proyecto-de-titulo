# Sistema web de toma de horas — CESFAM Eduardo Raggio Catemu

**Proyecto Final de Título · Ingeniería en Informática · Duoc UC**  
**Fecha de documentación:** 31-10-2026  
**Tecnologías base:** PHP, HTML, CSS, JavaScript y PostgreSQL.

## 1. Descripción
Aplicación web que proporciona un sitio público institucional y un formulario de solicitud de horas. Incluye un área privada para funcionarios autorizados y módulos de administración operativa y de contenidos institucionales, de acuerdo con el estado de cada módulo.

El backend PHP aplica las reglas de negocio, valida los datos y consulta PostgreSQL. El navegador presenta las vistas públicas y privadas. La disponibilidad debe verificarse en el servidor y revalidarse antes de registrar la reserva.

## 2. Alcance y exclusiones
- Solicitud pública de horas sin cuenta ciudadana.
- Datos del formulario conocidos: nombre, RUT, teléfono y correo opcional.
- Gestión interna con autenticación y roles `superadmin`, `administrador` y `funcionario`.
- Contenidos institucionales y biblioteca multimedia según módulos existentes.
- No incluye ficha clínica, diagnósticos ni tratamientos.
- No incluye chatbot funcional, WhatsApp, telefonía ni SMS.
- Docker está pospuesto y todavía no existe una configuración desplegable.

## 3. Requisitos previos
- Windows y XAMPP con Apache.
- PHP con PDO y el controlador `pdo_pgsql` habilitado.
- PostgreSQL instalado y en ejecución.
- Navegador moderno.
- PHPMailer se encuentra como biblioteca incluida en el proyecto, según la estructura local; confirmar la ruta exacta antes de ejecutar correo.

> Registrar las versiones reales con `php -v`, `httpd -v` y `psql --version` (si `psql` está en PATH). No asumir que todos los equipos tienen la misma versión.

## 4. Instalación local (referencia Windows)
1. Copiar el repositorio a `C:\xampp\htdocs\sistema toma de horas cesfam` o a la ruta definida por el equipo.
2. Iniciar Apache desde XAMPP.
3. Iniciar PostgreSQL como servicio del sistema.
4. Crear la base de datos `cesfam` y restaurar el respaldo/esquema autorizado del proyecto. **El paquete documental no incluye un dump SQL de producción.**
5. Revisar `config/conexion.php`: confirmar host, puerto, base de datos, usuario y contraseña del entorno local. El código conocido expone la conexión mediante `$conexion`.
6. Confirmar que PHP tenga habilitado `pdo_pgsql` y reiniciar Apache después de cambiar `php.ini`.
7. Revisar la ruta base utilizada por el proyecto. En el entorno conversado se ha utilizado `/sistema toma de horas cesfam`; no copiarla a un entorno distinto sin revisar enlaces y configuración.
8. Abrir el sitio desde `http://localhost/` seguido de la ruta local configurada.
9. Probar el acceso privado con una cuenta de desarrollo habilitada; no compartir credenciales en documentación o Git.

## 5. Configuración de correo
El proyecto utiliza PHPMailer/SMTP en los módulos relacionados. Revisar `config/correo.php`, `componentes/correos_reservas.php` y las rutas reales de la biblioteca antes de habilitar envíos. Las credenciales SMTP deben almacenarse fuera del repositorio y no se deben subir archivos locales que contengan secretos. Confirmar el envío real después de cada evento; que SMTP conecte no demuestra por sí solo que todos los disparadores estén integrados.

## 6. Estructura funcional conocida
```text
/
├── api/                 # Endpoints PHP; por ejemplo web_multimedia.php
├── componentes/         # Autenticación, navegación, utilidades y correo
├── config/              # Conexión y configuración local
├── css/                 # Hojas de estilo por módulo
├── js/                  # Scripts JavaScript separados
├── librerias/           # Bibliotecas de terceros, por ejemplo PHPMailer
├── publicos/            # Sitio institucional y toma de horas
└── funcionarios/        # Panel y módulos privados
```
La estructura es orientativa: contrastar con el árbol del repositorio actual antes de mover o crear directorios.

## 7. Seguridad mínima
- Validar datos en PHP, incluso si el formulario también valida en navegador.
- Usar consultas parametrizadas PDO.
- Validar sesión, rol y permisos en cada endpoint privado.
- Escapar contenido antes de imprimirlo en HTML.
- Validar archivos subidos por MIME real, tamaño, extensión y nombre; no permitir ejecución de archivos subidos.
- No imprimir contraseñas, cadenas de conexión, trazas ni mensajes SQL al visitante.
- Utilizar solo datos ficticios para desarrollo y pruebas.

## 8. Pruebas
Consultar `15_Plan_de_Pruebas.docx`. Los casos allí incluidos son un plan; sus estados deben actualizarse tras ejecutar pruebas reales. No registrar como aprobado un caso sin evidencia.

## 9. Estado de Docker
Docker se documenta como etapa futura. No se incluye `Dockerfile`, `compose.yaml` ni configuración de contenedores en este paquete. La contenedorización se debe abordar una vez estabilizados esquema, configuración, almacenamiento de archivos y dependencias.

## 10. Documentación
Los documentos del proyecto están numerados del 01 al 17. Consultar especialmente:
- `01_Documento_Inicio_Proyecto.docx`
- `08_SRS_Especificacion_Requisitos.docx`
- `10_Arquitectura.docx`
- `11_Modelo_de_Datos.docx`
- `13_Requisitos_No_Funcionales.docx`
- `15_Plan_de_Pruebas.docx`
- `16_Manual_Tecnico.docx`

## 11. Datos pendientes de confirmar antes de una entrega
- URL y reglas del repositorio Git.
- Versiones instaladas de PHP, Apache, PostgreSQL y bibliotecas.
- Dump/schema PostgreSQL vigente y columnas de todas las tablas.
- Claves foráneas, índices, restricciones y cardinalidades reales de agendas, horarios y reservas.
- Estado de cada módulo CMS y de los disparadores de correo.
- Responsable institucional y política de tratamiento de datos.

---

**Importante:** esta documentación refleja el alcance conocido al 31-10-2026. Las rutas y pasos deben contrastarse con la copia local exacta antes de una instalación en otro equipo.
