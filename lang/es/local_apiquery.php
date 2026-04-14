<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish language strings.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// ── General UI ────────────────────────────────────────────────────────────────
$string['pluginname']       = 'Api Query';
$string['manage_queries']   = 'Gestionar consultas API';
$string['execution_logs']   = 'Registros de ejecución';
$string['new_query']        = 'Nueva consulta';
$string['edit_query']       = 'Editar consulta';
$string['create']           = 'Crear';
$string['test']             = 'Probar';
$string['enable']           = 'Activar';
$string['disable']          = 'Desactivar';
$string['active']           = 'Activa';
$string['inactive']         = 'Inactiva';
$string['no_queries']       = 'No hay consultas configuradas. Haz clic en "Nueva consulta" para crear una.';
$string['shortname']        = 'Nombre corto';
$string['displayname']      = 'Nombre visible';
$string['description']      = 'Descripción';
$string['parameters']       = 'Parámetros';
$string['status']           = 'Estado';
$string['executions']       = 'Ejecuciones';
$string['last_execution']   = 'Último uso';
$string['avg_time']         = 'Tiempo promedio';
$string['actions']          = 'Acciones';
$string['query_created']    = 'Consulta creada correctamente.';
$string['query_updated']    = 'Consulta actualizada correctamente.';
$string['query_deleted']    = 'Consulta eliminada.';
$string['query_enabled']    = 'Consulta activada.';
$string['query_disabled']   = 'Consulta desactivada.';
$string['confirm_delete']   = '¿Estás seguro de que deseas eliminar esta consulta y sus registros?';
$string['api_usage']        = 'Uso de la API';
$string['api_usage_desc']   = 'Llama a cualquier consulta configurada usando el siguiente endpoint:';

// ── Capabilities ──────────────────────────────────────────────────────────────
$string['apiquery:execute'] = 'Ejecutar consultas API personalizadas';
$string['apiquery:manage']  = 'Gestionar consultas API personalizadas';

// ── Privacy API ───────────────────────────────────────────────────────────────
$string['privacy:metadata:local_apiquery_logs']             = 'Registros de ejecución de consultas API llamadas mediante token de servicio web.';
$string['privacy:metadata:local_apiquery_logs:userid']      = 'El ID del usuario cuyo token de servicio web fue usado para ejecutar la consulta.';
$string['privacy:metadata:local_apiquery_logs:params_used'] = 'Los parámetros enviados en la llamada a la API (puede incluir IDs de cursos o marcas de tiempo).';
$string['privacy:metadata:local_apiquery_logs:timecreated'] = 'La fecha y hora en que se realizó la llamada a la API.';

// ── DML warning (confirmation screen) ─────────────────────────────────────────
$string['warning_dml']          = 'Esta consulta contiene operaciones de modificación de datos (INSERT/UPDATE/DELETE/REPLACE). Úsela con precaución.';
$string['warning_dml_title']    = '⚠️ Esta query contiene operaciones que modifican datos en Moodle.';
$string['warning_dml_review']   = 'Revisa las advertencias antes de continuar:';
$string['confirm_dml']          = 'Entiendo los riesgos — Guardar de todas formas';
$string['back_to_edit']         = '← Volver y revisar';

// ── Validation errors (edit.php) ──────────────────────────────────────────────
$string['error_shortname_required']   = 'El shortname es requerido.';
$string['error_displayname_required'] = 'El nombre legible es requerido.';
$string['error_sql_required']         = 'La query SQL es requerida.';
$string['error_shortname_duplicate']  = 'El shortname \'{$a}\' ya está en uso por otra query.';

// ── Form sections (edit.php) ───────────────────────────────────────────────────
$string['section_identification'] = '1. Identificación de la función';
$string['section_sql']            = '2. Query SQL';
$string['section_params']         = '3. Parámetros de la función';

// ── Form field labels (edit.php) ──────────────────────────────────────────────
$string['field_shortname_hint']    = '(sin espacios, solo letras/números/guión bajo — será el nombre de la función en la API)';
$string['field_shortname_apicall'] = 'Se llamará como: <code>local_apiquery_execute_query</code> con el parámetro <code>shortname=<strong>este_valor</strong></code>';
$string['field_enabled_label']     = 'Activa (disponible en la API)';

// ── SQL editor (edit.php) ──────────────────────────────────────────────────────
$string['sql_security_title'] = '⚠️ Reglas de seguridad:';
$string['sql_security_desc']  = 'Solo <code>SELECT</code> está permitido. Usa parámetros <strong>nombrados</strong> (<code>:nombre</code>), no <code>?</code> posicionales. El prefijo <code>mdl_</code> se aplica automáticamente — escribe la tabla sin prefijo entre llaves: <code>{grade_grades}</code>.';

// ── Parameter table headers (edit.php) ────────────────────────────────────────
$string['param_col_name']     = 'Nombre del parámetro';
$string['param_col_type']     = 'Tipo';
$string['param_col_required'] = '¿Requerido?';
$string['param_col_default']  = 'Valor por defecto';
$string['add_param']          = '+ Agregar parámetro';

// ── Form input placeholder examples (edit.php) ───────────────────────────────
$string['placeholder_shortname_ex']   = 'Ej: get_grades_since';
$string['placeholder_displayname_ex'] = 'Ej: Calificaciones modificadas desde fecha';
$string['placeholder_description_ex'] = 'Qué retorna esta query y cuándo usarla...';
$string['placeholder_no_default']     = 'Vacío = sin default';
$string['placeholder_param_name']     = 'nombre_param';

// ── JS placeholder hints (edit.php) ───────────────────────────────────────────
$string['hint_placeholders_detected'] = 'Placeholders detectados: ';
$string['hint_placeholder_repeated']  = 'aparece más de una vez — se usará el mismo valor en todas las posiciones';
$string['hint_declare_once']          = '— declara cada uno UNA sola vez como parámetro abajo.';

// ── Test page (test.php) ──────────────────────────────────────────────────────
$string['test_title']           = 'Probar: {$a}';
$string['test_heading']         = '🧪 Probar: {$a}';
$string['back_to_list']         = '← Volver al listado';
$string['test_params_card']     = 'Parámetros de prueba';
$string['no_params_declared']   = 'Esta query no tiene parámetros declarados.';
$string['param_required_label'] = 'Requerido';
$string['param_optional_label'] = 'Opcional';
$string['execute_btn']          = '▶️ Ejecutar';
$string['test_success']         = 'Query ejecutada en {$a->ms} ms — {$a->rows} fila(s) retornadas.';
$string['no_rows_returned']     = 'La query no retornó filas con los parámetros dados.';
$string['showing_first_rows']   = 'Mostrando las primeras 100 filas de {$a} totales.';
$string['sql_card_title']       = 'SQL de la query';
$string['dml_success']          = 'DML ejecutado correctamente';
$string['error_param_required'] = 'El parámetro requerido \'{$a}\' no tiene valor.';

// ── Logs page (logs.php) ──────────────────────────────────────────────────────
$string['stat_total']   = 'Total ejecuciones';
$string['stat_avgtime'] = 'Tiempo promedio';
$string['stat_rows']    = 'Filas retornadas';
$string['stat_errors']  = 'Errores';
$string['no_logs_yet']  = 'No hay ejecuciones registradas aún.';
$string['col_function'] = 'Función';
$string['col_params']   = 'Parámetros usados';
$string['col_rows']     = 'Filas';
$string['col_time']     = 'Tiempo';
$string['col_status']   = 'Estado';
$string['col_date']     = 'Fecha';

// ── Export / Import ───────────────────────────────────────────────────────────
$string['export_queries']            = 'Exportar consultas';
$string['export_select_title']       = 'Selecciona las consultas a exportar';
$string['export_select_all']         = 'Seleccionar todo';
$string['export_deselect_all']       = 'Deseleccionar todo';
$string['export_selected_btn']       = 'Exportar seleccionadas';
$string['export_none_selected']      = 'Selecciona al menos una consulta para exportar.';
$string['export_col_select']         = 'Exportar';
$string['import_col_select']         = 'Importar';
$string['export_all']                = 'Exportar todo';
$string['import_queries']            = 'Importar consultas';
$string['import_file_label']         = 'Selecciona el archivo de exportación (.json)';
$string['import_preview_title']      = 'Vista previa de importación';
$string['import_confirm_btn']        = 'Confirmar importación';
$string['import_option_skip']        = 'Omitir — conservar la consulta existente';
$string['import_option_overwrite']   = 'Sobrescribir — reemplazar con la versión importada';
$string['import_conflict_mode']      = 'Cuando un nombre corto ya existe:';
$string['import_success']            = '{$a} consulta(s) importada(s) correctamente.';
$string['import_skipped']            = '{$a} consulta(s) omitida(s) (el nombre corto ya existe).';
$string['import_overwritten']        = '{$a} consulta(s) sobrescrita(s).';
$string['import_invalid_file']       = 'Archivo inválido. Por favor sube un archivo JSON de exportación de este plugin.';
$string['import_no_queries']         = 'El archivo no contiene consultas.';
$string['import_source_info']        = 'Origen: {$a->site} &mdash; Moodle {$a->release} &mdash; Exportado el {$a->date}';
$string['import_col_status']         = 'Estado de importación';
$string['import_status_new']         = 'Nueva';
$string['import_status_exists']      = 'Ya existe';
$string['warning_version_mismatch']         = 'Diferencia de versión de Moodle';
$string['warning_version_mismatch_desc']    = 'Este archivo fue exportado desde Moodle {$a->exported} pero este sitio usa Moodle {$a->current}. Entre versiones, Moodle puede renombrar tablas, mover columnas o cambiar estructuras de datos. Revisa cuidadosamente cada consulta importada y pruébala antes de activarla en producción.';
$string['warning_version_major_mismatch']      = 'Diferencia de versión mayor detectada';
$string['warning_version_major_mismatch_desc'] = 'El archivo fue exportado desde Moodle {$a->exported} y este sitio usa Moodle {$a->current}. Esta es una diferencia de versión significativa. Las consultas SQL pueden referenciar tablas o columnas que ya no existen o que fueron reestructuradas. Se recomienda fuertemente: importar como desactivadas y probar cada consulta individualmente.';

// ── Webservice API runtime messages (external.php) ───────────────────────────
$string['error_query_execute']         = 'Error al ejecutar la query: {$a}';
$string['error_required_param_missing']= 'El parámetro requerido \'{$a}\' no fue enviado.';

// ── Webservice parameter/return descriptions (external.php) ──────────────────
$string['ws_shortname_desc']     = 'Nombre corto de la query a ejecutar';
$string['ws_param_name_desc']    = 'Nombre del parámetro';
$string['ws_param_value_desc']   = 'Valor del parámetro';
$string['ws_params_desc']        = 'Parámetros de la query';
$string['ws_ret_success']        = 'Si la ejecución fue exitosa';
$string['ws_ret_shortname']      = 'Nombre de la query ejecutada';
$string['ws_ret_rows_count']     = 'Cantidad de filas retornadas';
$string['ws_ret_exec_ms']        = 'Tiempo de ejecución en milisegundos';
$string['ws_ret_field_key']      = 'Nombre del campo';
$string['ws_ret_field_value']    = 'Valor del campo';
$string['ws_ret_rows']           = 'Filas de resultado — cada fila es un array de {key, value}';
$string['ws_ret_error']          = 'Mensaje de error si success=false';
$string['ws_list_shortname']     = 'Identificador de la query';
$string['ws_list_param_type']    = 'Tipo: int, text, float, bool';
$string['ws_list_param_required']= 'Si es requerido';

// ── SQL Validator messages (sql_validator.php) ────────────────────────────────
$string['error_forbidden_keyword']   = 'Operación prohibida: \'{$a}\'. Esta operación puede dañar datos del sistema de forma irreversible.';
$string['warning_keyword_dml']       = '⚠️ La query contiene \'{$a}\' — esta operación modifica datos en Moodle. Asegúrate de que sea intencional y pruébala primero en un entorno de desarrollo.';
$string['error_forbidden_table']     = 'Acceso prohibido a la tabla \'{$a}\'. Esta tabla contiene datos sensibles del sistema.';
$string['error_multiple_statements'] = 'Solo se permite una operación por función. No uses punto y coma (;) para encadenar múltiples statements.';
$string['error_positional_params']   = 'Usa parámetros nombrados (:nombre) en lugar de ? posicionales. Ejemplo: WHERE id = :userid';
$string['error_param_undeclared']    = 'El placeholder \':{$a}\' está en el SQL pero no está declarado como parámetro.';
$string['error_param_notinquery']    = 'El parámetro \'{$a}\' está declarado pero no aparece en el SQL.';
