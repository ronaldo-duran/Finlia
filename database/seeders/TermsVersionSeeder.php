<?php

namespace Database\Seeders;

use App\Models\TermsVersion;
use Illuminate\Database\Seeder;

class TermsVersionSeeder extends Seeder
{
    /**
     * Publica la versión vigente de los términos y condiciones (Plan 03).
     *
     * ⚠️ ESTA FILA NO SE EDITA JAMÁS. Cada aceptación de un usuario apunta a
     * una versión concreta y es prueba de consentimiento (ADR-0031): cambiar
     * el texto de una versión ya publicada destruiría esa prueba. Para
     * modificar los términos se publica OTRA versión — una fila nueva, con su
     * `change_summary` — y la app vuelve a pedir aceptación.
     *
     * El texto lo aprueba el dueño del producto. Conviene que un abogado lo
     * revise antes de cobrar por el servicio.
     */
    public function run(): void
    {
        if (TermsVersion::query()->exists()) {
            return;
        }

        TermsVersion::create([
            'version' => '2026-09-v1',
            'title' => 'Términos y condiciones de uso',
            'change_summary' => null,
            'published_at' => now(),
            'content' => implode("\n\n", [
                'Última actualización: 10 de septiembre de 2026. Versión 2026-09-v1.',

                'Al crear una cuenta en Finlia aceptas estos términos. Si no estás de acuerdo con alguno, no uses el servicio. Están escritos para que se entiendan: si algo no te queda claro, escríbenos y te lo explicamos.',

                '1. QUIÉN PRESTA EL SERVICIO',

                'Finlia es operado por Ronaldo Durán, persona natural domiciliada en Colombia, a través del sitio finlia.online y de la aplicación disponible en app.finlia.online. Para cualquier asunto relacionado con estos términos o con tus datos, el canal de contacto es contacto@finlia.online y el formulario disponible en finlia.online/contacto.',

                '2. QUÉ ES FINLIA — Y QUÉ NO ES',

                'Finlia es una herramienta para que una persona o una familia registre sus ingresos, gastos, deudas, presupuestos y metas de ahorro, y pueda estimar cuánto dinero tiene realmente disponible.',

                'Finlia NO es una entidad financiera ni vigilada por la Superintendencia Financiera de Colombia. No capta ni administra dinero del público, no realiza pagos, no otorga créditos y no intermedia operaciones financieras. No se conecta a tus cuentas bancarias ni te pedirá nunca las claves de tu banco: los movimientos los registras tú.',

                'Finlia NO presta asesoría financiera, de inversión, contable, tributaria ni legal. Las cifras que calcula —el dinero disponible, la cuota de una deuda, la fecha estimada en que terminarías de pagarla, el aporte mensual sugerido para una meta— son ESTIMACIONES basadas en la información que tú ingresas y en fórmulas estándar. Tu banco o tu entidad de crédito pueden aplicar reglas distintas (seguros, cuota de manejo, días de mora, redondeos, compras nuevas), así que los valores reales pueden variar. Úsalas como guía para decidir, nunca como estado de cuenta ni como recomendación profesional. Las decisiones sobre tu dinero son tuyas y de tu entera responsabilidad.',

                '3. QUIÉN PUEDE USAR FINLIA',

                'Debes ser mayor de 18 años y tener capacidad legal para obligarte. Al registrarte declaras que cumples ambos requisitos. Si detectamos que una cuenta pertenece a un menor de edad, la suspenderemos y eliminaremos sus datos.',

                'La información que registres debe ser veraz. Necesitas un correo electrónico válido y verificado: sin verificarlo no podrás usar la aplicación.',

                '4. TU CUENTA',

                'Eres responsable de mantener tu contraseña en secreto y de toda la actividad realizada desde tu cuenta. Si sospechas que alguien accedió sin tu autorización, cambia tu contraseña de inmediato —eso cierra las sesiones abiertas en otros dispositivos— y avísanos.',

                'Nunca te pediremos tu contraseña por correo, por teléfono ni por ningún canal distinto al formulario de inicio de sesión de la aplicación.',

                '5. HOGARES COMPARTIDOS — LÉELO CON ATENCIÓN',

                'Finlia permite crear un hogar e invitar a otras personas. Todos los miembros de un hogar VEN LA MISMA INFORMACIÓN FINANCIERA: cuentas, movimientos, deudas, presupuestos y metas. No hay información privada dentro de un hogar.',

                'Invita únicamente a personas con las que quieras compartir esa información. Quien administra el hogar puede invitar y retirar miembros. Retirar a alguien le quita el acceso hacia adelante, pero los movimientos que haya registrado permanecen en la historia del hogar, porque esa historia es de todos sus miembros y no solo de quien se va.',

                'Ningún usuario puede ver los datos de un hogar al que no pertenece.',

                '6. TUS DATOS PERSONALES',

                'El tratamiento de tus datos se rige por la Ley 1581 de 2012, el Decreto 1377 de 2013 y demás normas colombianas aplicables. Al aceptar estos términos y la política de tratamiento de datos autorizas de manera previa, expresa e informada que Finlia recolecte, almacene, use y suprima tus datos personales y financieros con las finalidades allí descritas: prestarte el servicio, calcular tus cifras, enviarte los correos imprescindibles de la cuenta y los recordatorios que hayas solicitado, y atender lo que nos escribas.',

                'Como titular tienes derecho a conocer, actualizar y rectificar tus datos; a solicitar prueba de esta autorización; a ser informado sobre el uso que se les ha dado; a presentar quejas ante la Superintendencia de Industria y Comercio; a revocar la autorización y solicitar la supresión de tus datos cuando no exista un deber legal de conservarlos; y a acceder gratuitamente a ellos.',

                'Para ejercerlos escríbenos a contacto@finlia.online. Las consultas se atienden en un máximo de diez (10) días hábiles, prorrogables por cinco (5) más; los reclamos, en quince (15) días hábiles, prorrogables por ocho (8), informándote siempre el motivo de la prórroga.',

                'Puedes descargar todos tus datos en cualquier momento desde tu perfil, en formato abierto. Finlia no vende ni cede tu información a terceros con fines comerciales ni publicitarios. El detalle completo está en la política de tratamiento de datos, disponible en finlia.online/datos, que forma parte de estos términos.',

                '7. USO ACEPTABLE',

                'No puedes usar Finlia para actividades ilícitas, ni intentar acceder a datos de otros usuarios o de hogares ajenos, ni vulnerar o probar la seguridad del servicio sin autorización escrita, ni interferir con su funcionamiento, ni automatizar su uso de forma que degrade el servicio para los demás, ni suplantar a otra persona.',

                'Si encuentras una vulnerabilidad, te agradecemos que nos escribas a contacto@finlia.online antes de divulgarla. Responderemos y te daremos crédito si así lo quieres.',

                '8. DISPONIBILIDAD DEL SERVICIO',

                'Finlia se presta "tal cual" y "según disponibilidad". Trabajamos para que funcione y para conservar tu información, pero no garantizamos que el servicio esté disponible de forma ininterrumpida ni libre de errores. Puede haber interrupciones por mantenimiento, fallas técnicas o causas ajenas a nuestro control.',

                'Por eso te recomendamos descargar tus datos periódicamente desde tu perfil. Es tu copia, y es la mejor protección frente a cualquier imprevisto.',

                'Podemos modificar, suspender o descontinuar funcionalidades. Si un cambio te afecta de forma significativa, te avisaremos con antelación razonable al correo de tu cuenta. Si decidiéramos cerrar el servicio, te avisaremos con al menos noventa (90) días de antelación y la exportación de datos permanecerá disponible durante todo ese plazo.',

                '9. PRECIO',

                'Hoy Finlia es gratuito. En el futuro podrán existir funcionalidades de pago; en ese caso se anunciarán con claridad y su precio y condiciones se informarán antes de cualquier cobro. Nunca se te cobrará sin tu autorización expresa.',

                '10. EL SOFTWARE Y LA MARCA',

                'El código fuente de Finlia es software libre publicado bajo la licencia GNU Affero General Public License versión 3 o posterior, disponible en github.com/ronaldo-duran/Finlia. Esa licencia te da derechos sobre el CÓDIGO —usarlo, estudiarlo, modificarlo y redistribuirlo en los términos de la AGPL—, pero no sobre este servicio ni sobre los datos de otras personas.',

                'El nombre "Finlia", su logotipo y su identidad visual no se ceden bajo esa licencia y no pueden usarse de forma que sugiera afiliación o respaldo sin autorización escrita.',

                'La información que tú registras es tuya. No adquirimos derechos de propiedad sobre ella; solo la tratamos para prestarte el servicio.',

                '11. TERMINACIÓN',

                'Puedes eliminar tu cuenta cuando quieras desde tu perfil. La cuenta queda suspendida treinta (30) días, durante los cuales puedes reactivarla iniciando sesión; cumplido el plazo, tus datos personales se eliminan de forma definitiva.',

                'Podemos suspender o cerrar una cuenta que incumpla gravemente estos términos. Salvo que exista un riesgo de seguridad o una obligación legal que lo impida, te avisaremos antes y te daremos la oportunidad de descargar tus datos.',

                '12. RESPONSABILIDAD',

                'Finlia es una herramienta de organización. No respondemos por las decisiones económicas que tomes, ni por perjuicios derivados de información que hayas registrado de forma incompleta o incorrecta, ni por el uso que otros miembros de tu hogar hagan de la información compartida, ni por fallas de tu conexión, tu dispositivo o servicios de terceros.',

                'Nada en estos términos limita nuestra responsabilidad por dolo o culpa grave, ni los derechos que la ley colombiana te reconoce como consumidor y como titular de datos personales. Ninguna cláusula de este documento debe interpretarse en un sentido que los desconozca.',

                '13. CAMBIOS EN ESTOS TÉRMINOS',

                'Si actualizamos estos términos publicaremos una versión nueva, con un resumen de lo que cambió, y te pediremos aceptarla para seguir usando la aplicación. Las versiones anteriores quedan consultables públicamente, y cada aceptación queda registrada con su versión y su fecha.',

                'Rechazar una versión nueva no elimina tu cuenta ni tus datos: simplemente no podrás seguir usando la aplicación hasta que la aceptes, y conservas el derecho a exportar tu información y a eliminar tu cuenta.',

                '14. LEY APLICABLE',

                'Estos términos se rigen por las leyes de la República de Colombia. Cualquier controversia se someterá a los jueces competentes colombianos, sin perjuicio de las acciones que como consumidor puedas presentar ante la Superintendencia de Industria y Comercio.',

                '15. CONTACTO',

                'Escríbenos a contacto@finlia.online o desde finlia.online/contacto. Respondemos.',
            ]),
        ]);
    }
}
