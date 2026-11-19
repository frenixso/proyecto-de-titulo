# Índice de documentación — CESFAM Eduardo Raggio Catemu

Fecha de corte: 31-10-2026. Los 17 documentos se reconstruyeron con el alcance vigente conocido. Los elementos no acreditados se identifican como pendientes de validación. El archivo README.md es la guía destinada al repositorio; los documentos Word contienen la versión formal académica.

| N.º | Documento | Estado documental |
|---:|---|---|
| 01 | Documento de inicio del proyecto | Nuevo, requiere validación institucional |
| 02 | Metodología declarada y justificada | Nuevo, propuesta iterativa con prácticas Scrum |
| 03 | Product Vision | Nuevo, alcance web público e interno |
| 04 | Product Backlog | Nuevo, prioridad y estado por elemento |
| 05 | Sprint Backlog | Propuesta para siguiente iteración; no es sprint ejecutado |
| 06 | Definition of Done | Nuevo |
| 07 | Retrospectivas | Plantilla; sin resultados inventados |
| 08 | SRS | Nuevo; requisitos por validar |
| 09 | README | README.md + copia Word |
| 10 | Arquitectura | Nuevo; Docker solo futuro |
| 11 | Modelo de datos | Preliminar; falta exportación del esquema PostgreSQL actual |
| 12 | Diagramas UML | Nuevo; relaciones conceptuales por validar |
| 13 | Requisitos no funcionales | Nuevo; objetivos verificables |
| 14 | Docker | Plan documental; implementación pospuesta |
| 15 | Pruebas | Casos propuestos; sin resultados simulados |
| 16 | Manual técnico | Nuevo; rutas/versions requieren contraste local |
| 17 | Innovación | Nuevo; impacto pendiente de medición |

## Decisiones que se mantuvieron
- El producto es el Sistema web de toma de horas y administración de contenidos del CESFAM Eduardo Raggio Catemu.
- La reserva pública no requiere cuenta ciudadana.
- No se incluye ficha clínica ni historial médico.
- No se presenta el chatbot de demostración como parte desarrollada del producto.
- No se prometen integraciones WhatsApp, telefonía o SMS; SMS queda fuera del alcance actual.
- Se mantiene PHP, HTML, CSS, JavaScript separado y PostgreSQL; no se utiliza Python.
- El entorno actual conocido es XAMPP/Apache + PostgreSQL; Docker se implementará después.

## Pendientes de validación técnica
1. Exportar el esquema PostgreSQL actual sin datos personales y actualizar el modelo de datos.
2. Confirmar relación `agendas`–`horarios` y reglas de cupos; en una revisión del 27-10-2026 se observaron 0 agendas, 6 horarios, 6 reservas y 6 horarios sin agenda asociada.
3. Confirmar columnas/estados de reservas, funcionarios, CMS y tablas de auditoría.
4. Confirmar estado de cada módulo CMS y cada disparador de correo.
5. Registrar las versiones instaladas de PHP, Apache, PostgreSQL y bibliotecas.
6. Completar responsables, calendario real, repositorio y retrospectivas con evidencias.
7. Validar aviso de privacidad, conservación y normativa aplicable con el CESFAM. La Ley 21.719 tiene fecha de entrada en vigencia diferida al 01-12-2026.
