# Drupal Headless Module: Tutorial Completo

## ¿Qué es este módulo y por qué existe?

Imagina que tienes un sitio web en Drupal con excelente contenido: artículos, páginas, imágenes, videos. Ahora quieres crear una aplicación moderna con Next.js, React, o cualquier otro framework frontend. Tradicionalmente, conectar Drupal (el backend) con tu frontend moderno era un proceso complejo, manual y propenso a errores que podía tomar hasta **100 minutos de configuración**.

**Drupal Headless Module** es la solución: un módulo que transforma esas 100 minutos en solo **5 minutos**, automatizando y simplificando todo el proceso de configuración.

## El Problema que Resuelve

Cuando desarrolladores intentaban hacer Drupal "headless" (separar el backend del frontend), se enfrentaban a estos desafíos:

1. **Configuración OAuth2 manual**: Tenían que ejecutar comandos OpenSSL en la terminal para generar claves criptográficas
2. **Secretos perdidos**: Los API secrets se mostraban una vez y luego desaparecían para siempre
3. **Múltiples formularios desconectados**: Configuración dispersa en diferentes páginas sin guía
4. **Sin preview**: Los editores de contenido no podían ver cómo se vería su trabajo antes de publicar
5. **Sin notificaciones**: El frontend nunca sabía cuándo el contenido cambiaba
6. **Configuración a ciegas**: Sin forma de verificar si todo funcionaba correctamente

## Cómo Está Construido: Arquitectura del Módulo

El módulo está organizado en **capas lógicas** que trabajan juntas:

### 1. Servicios (Services)
El corazón del módulo son sus 6 servicios principales:

**OAuth2KeyManager**: Genera automáticamente las claves RSA-2048 necesarias para autenticación OAuth2. Elimina la necesidad de comandos OpenSSL manuales.

**ConsumerManager**: Gestiona los "consumidores" (tus aplicaciones frontend). Permite crear, listar y eliminar consumers con un par de clicks.

**ConfigurationManager**: Centraliza toda la configuración del módulo (CORS, rate limiting, preview URLs, etc.) en un solo lugar.

**WebhookManager**: Sistema de notificaciones que avisa a tu frontend cuando el contenido cambia. Incluye cola de reintentos, firma HMAC para seguridad, y logs de entrega.

**PreviewManager**: Genera tokens temporales (válidos 1 hora) que permiten a editores ver contenido no publicado en el frontend.

**HealthCheckManager**: Verifica el estado de 9 checkpoints críticos y puede auto-reparar problemas con un click.

### 2. Controladores (Controllers)
Los controladores manejan las páginas de administración:

**DashboardController**: Muestra un dashboard central con el estado del sistema y progreso de setup.

**ChecklistController**: Presenta una lista de verificación interactiva con acciones de auto-fix.

**ApiTestController**: Herramienta integrada para probar OAuth2, JSON:API y CORS sin necesidad de Postman.

**WebhookController**: Interfaz para configurar y monitorear webhooks.

**PreviewController**: Genera URLs de preview y valida tokens.

### 3. Formularios (Forms)
Los formularios simplifican tareas complejas:

**SetupWizardForm**: Wizard de 5 pasos que guía desde cero hasta el primer API call.

**CreateConsumerForm**: Crea consumers y muestra los secrets UNA sola vez con botones de copiar.

**GenerateKeysForm**: Genera claves OAuth2 con validación automática.

**WebhookForm**: Configura webhooks con filtros por tipo de entidad y eventos.

**SettingsForm**: Configuración centralizada de CORS, rate limiting, y preview URLs.

## Qué Permite Hacer al Usuario

### Para Administradores: Setup en 3 Formas

**Opción 1: Setup Checklist (5 minutos - Recomendado)**

Accedes a `/admin/drupal-headless/checklist` y ves una lista visual:

```
Setup Progress: 45%
████████████░░░░░░░░░░

Critical Items:
✓ JSON:API Module          [Complete]
✗ OAuth2 Keys              [Fix Automatically]
✓ Consumers Module         [Complete]
⚠ CORS Configuration       [Fix Automatically]
```

Haces click en "Fix Automatically" junto a OAuth2 Keys:
- El sistema genera las claves RSA-2048 automáticamente
- Configura Simple OAuth
- Actualiza el progreso a 67%

Haces click en "Fix Automatically" junto a CORS:
- Habilita CORS
- Configura localhost:3000 y localhost:4321 por defecto
- Progreso ahora 89%

Click en "Configure" junto a API Consumer:
- Te lleva directamente al formulario correcto
- Llenas nombre y seleccionas "Next.js"
- El sistema genera UUID y secret
- Te muestra las credenciales CON botones de copiar
- Genera un archivo .env listo para tu proyecto Next.js

**Opción 2: Setup Wizard (12 minutos)**

Un wizard paso a paso:

```
Paso 1: Validación de Entorno
- Verifica módulos requeridos
- Verifica sistema de archivos privados
- Verifica OpenSSL

Paso 2: Generación de Claves OAuth2
- Un click y las claves se generan automáticamente
- Muestra ubicación y estado

Paso 3: Creación de Consumer
- Formulario simplificado
- Selección de framework (Next.js, React, Vue, Astro)
- Muestra credenciales una sola vez

Paso 4: Configuración CORS
- Checkbox para habilitar
- Lista de orígenes permitidos
- Opción de rate limiting

Paso 5: Resumen y Credenciales
- Muestra todas las configuraciones
- URLs de token y API
- Instrucciones de uso
```

**Opción 3: Configuración Manual**

Para usuarios avanzados que prefieren control total.

### Para Content Editors: Preview y Publicación

Los editores de contenido ahora tienen un botón "Preview" en cada formulario de edición:

1. Escriben un artículo nuevo (aún no publicado)
2. Click en "Preview"
3. Se genera un token temporal
4. Se abre el frontend en nueva pestaña
5. Ven exactamente cómo se verá el artículo
6. Hacen ajustes si es necesario
7. Publican con confianza

El frontend recibe el token, lo valida con Drupal, y muestra el contenido no publicado.

### Para Desarrolladores Frontend: APIs Listas

Tu proyecto Next.js recibe un archivo `.env` completo:

```bash
NEXT_PUBLIC_DRUPAL_BASE_URL=https://tu-drupal.com
DRUPAL_CLIENT_ID=abc-123-def-456
DRUPAL_CLIENT_SECRET=xyz-789-secret
```

Y código de ejemplo para autenticación:

```javascript
// Obtener token OAuth2
const tokenResponse = await fetch(
  `${process.env.NEXT_PUBLIC_DRUPAL_BASE_URL}/oauth/token`,
  {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      grant_type: 'client_credentials',
      client_id: process.env.DRUPAL_CLIENT_ID,
      client_secret: process.env.DRUPAL_CLIENT_SECRET,
    })
  }
);

const { access_token } = await tokenResponse.json();

// Usar token para obtener contenido
const articles = await fetch(
  `${process.env.NEXT_PUBLIC_DRUPAL_BASE_URL}/jsonapi/node/article`,
  {
    headers: { 'Authorization': `Bearer ${access_token}` }
  }
);
```

### Para Todos: Webhooks Automáticos

Configuras un webhook a `https://tu-frontend.com/api/revalidate`:

1. Un editor publica un artículo en Drupal
2. El módulo dispara un webhook automáticamente
3. Tu frontend recibe la notificación:
   ```json
   {
     "event": "update",
     "entity_type": "node",
     "entity_uuid": "abc-123",
     "entity_label": "Mi Artículo Actualizado",
     "timestamp": 1234567890
   }
   ```
4. Tu frontend invalida el cache
5. El nuevo contenido aparece instantáneamente

## Herramientas de Debug Integradas

### API Tester
Sin salir de Drupal, puedes probar:

- **OAuth2 Token**: Ingresa consumer ID y secret, click "Test" → Ve el token
- **JSON:API Access**: Usa el token obtenido para probar el endpoint
- **CORS Headers**: Verifica que CORS esté configurado correctamente

### Webhook Logs
Ve el historial completo de webhooks:

```
Timestamp              URL                      Status    Payload
2024-01-15 10:30:25   frontend.com/api/hook   ✓ 200     update: article
2024-01-15 10:28:10   frontend.com/api/hook   ✗ 500     create: page
```

Click en el error para ver detalles y diagnosticar problemas.

## Resultados Medibles

**Antes del módulo:**
- 25 minutos: Generar claves OAuth2 manualmente
- 20 minutos: Configurar CORS y consumers
- 30 minutos: Configurar webhooks
- 15 minutos: Probar y debuggear
- 10 minutos: Configurar preview
- **Total: ~100 minutos**

**Con el módulo:**
- 1 minuto: Abrir checklist
- 2 minutos: Clicks en "Fix Automatically"
- 2 minutos: Crear consumer y copiar credenciales
- **Total: ~5 minutos**

**Reducción del 95% en tiempo de configuración.**

## Conclusión

Drupal Headless Module no es solo un conjunto de utilidades: es una solución completa que transforma la experiencia de trabajar con Drupal headless. Combina automatización inteligente, guías paso a paso, y herramientas de debug en un paquete cohesivo que reduce dramáticamente la fricción y los errores.

Ya seas administrador, desarrollador, o editor de contenido, el módulo te proporciona exactamente lo que necesitas en el momento que lo necesitas, sin configuración manual tediosa ni comandos de terminal intimidantes.

**El objetivo: Que crear una aplicación headless moderna con Drupal sea tan simple como instalar un tema.**
