# User Journey: Drupal Headless Module

## Basado en la Investigación de Pain Points y Necesidades Reales

Este documento detalla el journey completo de uso del módulo, exponiendo gaps y necesidades no cubiertas.

---

## Persona 1: Alex - Full-Stack Developer (Proyecto Next.js + Drupal)

### Contexto
Alex trabaja en una agencia. Cliente necesita un sitio corporativo con Drupal como CMS y Next.js en el frontend (hospedado en Vercel). Alex ha hecho headless Drupal antes y conoce el dolor: configurar OAuth2 manualmente, generar keys, configurar CORS, crear consumers, troubleshoot errores de autenticación, etc. Busca simplificar esto.

---

## FASE 1: INSTALACIÓN Y ACTIVACIÓN

### 1.1 Instalación del Módulo

**Acción:** Alex instala el módulo via Composer
```bash
composer require drupal/drupal_headless
```

**Expectativa:**
- Que se instalen dependencias automáticamente (consumers, simple_oauth, jsonapi)
- Que no haya errores de dependencias

**Realidad Actual:**
✅ Composer instala el módulo correctamente
✅ Las dependencias están definidas en composer.json

**Gap Identificado:**
❌ **No hay validación de versiones de PHP o Drupal durante composer install**
❌ **No hay post-install script que sugiera próximos pasos**

---

### 1.2 Activación del Módulo

**Acción:** Alex activa el módulo
```bash
drush en drupal_headless
```

**Expectativa:**
- Que el módulo valide que JSON:API, Consumers y Simple OAuth estén habilitados
- Mensaje claro sobre qué hacer después
- Que se cree configuración inicial

**Realidad Actual:**
✅ Hook install ejecuta y verifica private file system
✅ Muestra mensaje de éxito con link a settings

**Gaps Identificados:**
❌ **No habilita automáticamente JSON:API, Consumers, Simple OAuth** (debería hacerlo)
❌ **No crea configuración CORS básica** (debería preguntar o detectar)
❌ **No genera OAuth2 keys automáticamente** (pain point crítico en investigación)
❌ **No crea un consumer "default" para testing rápido**
❌ **El mensaje de éxito no es accionable** (no dice exactamente qué hacer paso 1, 2, 3)

---

### 1.3 Verificación Post-Instalación

**Acción:** Alex va a `/admin/reports/status`

**Expectativa:**
- Ver check verde para private file system
- Ver check verde para dependencias
- Ver warning si falta algo crítico

**Realidad Actual:**
✅ Se muestra error si private file system no configurado
✅ Se validan módulos requeridos

**Gaps Identificados:**
❌ **No valida que OAuth2 keys existan**
❌ **No valida que CORS esté configurado correctamente**
❌ **No valida que JSON:API esté accesible** (/jsonapi endpoint)
❌ **No ofrece fix automático** (botón "Configure automatically")

---

## FASE 2: CONFIGURACIÓN INICIAL

### 2.1 Configuración de Private File System (Pre-requisito)

**Acción:** Alex necesita configurar private files (si no lo tiene)

**Expectativa:**
- Instrucciones claras sobre dónde y cómo
- Comando drush para crear el directorio
- Validación automática después

**Realidad Actual:**
⚠️ Solo muestra warning genérico
❌ **No incluye:**
  - Script de setup automático
  - Instrucciones específicas para diferentes entornos (DDEV, Lando, Acquia, Pantheon)
  - Validación post-configuración

**Gap Crítico:**
❌ **Usuario novato se bloquea aquí** - el warning no es suficientemente accionable

---

### 2.2 Acceso al Dashboard

**Acción:** Alex va a `/admin/drupal-headless/dashboard`

**Expectativa:**
- Ver estado del sistema en un vistazo
- Botones de acción rápida ("Create Consumer", "Generate Keys", etc.)
- Links a documentación relevante
- Información sobre su instalación específica

**Realidad Actual:**
✅ Muestra estado de dependencias
✅ Muestra estado CORS y rate limiting
✅ Lista consumers existentes
✅ Links rápidos a configuración

**Gaps Identificados:**
❌ **No muestra estado de OAuth2 keys** (crítico)
❌ **No muestra si JSON:API es accesible públicamente**
❌ **No muestra sample API calls** para testing
❌ **No tiene "Quick Start Wizard"** (multi-step setup)
❌ **No muestra versión de Drupal/módulo**
❌ **No tiene health check endpoint** (/drupal-headless/health)
❌ **No detecta framework del frontend** (si ya hay uno corriendo)

---

### 2.3 Configuración General

**Acción:** Alex va a `/admin/config/services/drupal-headless`

**Expectativa:**
- Wizard paso a paso (no form masivo)
- Explicaciones inline de cada campo
- Presets por tipo de proyecto ("Next.js on Vercel", "React SPA", etc.)
- Validación en tiempo real

**Realidad Actual:**
✅ Form con todas las opciones organizadas
✅ CORS configuration
✅ Rate limiting
✅ OAuth2 settings

**Gaps Críticos:**
❌ **No es un wizard** - es un form tradicional (intimidante para novatos)
❌ **No hay presets/templates** ("Quick Setup for Next.js", etc.)
❌ **CORS origins requiere entrada manual** (no detecta desde donde viene el request)
❌ **No valida CORS origins en tiempo real** (formato URL, accesibilidad)
❌ **Rate limiting no muestra impacto estimado** (¿es suficiente para mi uso?)
❌ **No hay "Development Mode"** que deshabilite rate limiting y sea más permisivo
❌ **No explica qué es cada setting** (necesita help text mejor)

---

## FASE 3: SETUP DE OAUTH2 (Pain Point #1 de Investigación)

### 3.1 Generación de Keys

**Acción:** Alex necesita generar OAuth2 keys (private.key, public.key)

**Expectativa (Según Investigación):**
- Debería ser automático o con 1 click
- El módulo debería generarlas y almacenarlas de forma segura
- Debería mostrar dónde están guardadas
- Debería validar que funcionan

**Realidad Actual:**
❌ **CRÍTICO: El módulo NO genera las keys automáticamente**
❌ **El usuario debe hacerlo manualmente con OpenSSL:**
```bash
cd private/oauth_keys
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout > public.key
```

**Gap Crítico Detectado:**
Este es un **pain point masivo** identificado en la investigación. El módulo debería:
❌ Generar keys al instalar (si private path existe)
❌ Ofrecer botón "Generate Keys Now" en dashboard
❌ Validar que las keys sean válidas
❌ Mostrar fingerprint de las keys
❌ Permitir regenerar keys (con warning)
❌ Exportar public key para compartir con frontend

**Impacto:**
🔴 **BLOCKER para usuarios no técnicos**
🔴 **Frustrante incluso para usuarios técnicos** (por qué debo hacer esto manualmente?)

---

### 3.2 Configuración de Simple OAuth

**Acción:** Alex debe configurar Simple OAuth para usar las keys generadas

**Expectativa:**
- El módulo Drupal Headless debería hacer esto automáticamente
- O al menos ofrecer un botón "Configure OAuth2"

**Realidad Actual:**
❌ **Usuario debe ir manualmente a** `/admin/config/people/simple_oauth`
❌ **Usuario debe copiar paths manualmente:**
  - Public key path: `../private/oauth_keys/public.key`
  - Private key path: `../private/oauth_keys/private.key`

**Gap Crítico:**
❌ **Drupal Headless Module no auto-configura Simple OAuth**
❌ **No valida que la configuración sea correcta**
❌ **No muestra instrucciones de cómo hacerlo**

**Impacto:**
🔴 **Usuario novato NO sabe que debe hacer este paso**
🔴 **Autenticación fallará silenciosamente después**

---

## FASE 4: CREACIÓN DE CONSUMER (API Client)

### 4.1 Crear Consumer para Next.js App

**Acción:** Alex necesita crear un consumer para su app Next.js

**Expectativa (de investigación - Next.js for Drupal lo hace así):**
- Wizard que pregunta: "What framework?" → Next.js
- Pide: Label, Description, Frontend URL
- Auto-genera: Client ID (UUID), Client Secret
- Auto-configura: Roles, Scopes
- Muestra: Credentials para copiar (.env file ready)

**Realidad Actual:**
Usuario debe ir a `/admin/config/services/consumer` (Consumers module)
O usar el servicio `drupal_headless.consumer_manager` programáticamente

**Vía Programática:**
```php
$consumer = \Drupal::service('drupal_headless.consumer_manager')
  ->createConsumer('Next.js Frontend', 'Production app');
```

**Gaps Críticos:**
❌ **No hay UI en Drupal Headless Module para crear consumers**
❌ **Usuario debe conocer el módulo Consumers** (¿por qué?)
❌ **No hay template/wizard "Create Consumer for Next.js"**
❌ **No auto-genera .env file** con las credenciales
❌ **No explica qué roles/scopes dar al consumer**
❌ **Secret no es mostrado en plain text** (es encrypted, ¿cómo lo recupero?)

**Impacto:**
🔴 **Usuario frustrado** - "pensé que esto iba a ser fácil"
🔴 **Muchos pasos manuales** - el pain point principal según investigación

---

### 4.2 Copiar Credenciales al Frontend

**Acción:** Alex necesita copiar Client ID y Secret a su app Next.js

**Expectativa:**
- Dashboard muestra consumers con "Copy Credentials" button
- Click → copia formato .env:
  ```
  DRUPAL_CLIENT_ID=uuid-here
  DRUPAL_CLIENT_SECRET=secret-here
  NEXT_PUBLIC_DRUPAL_BASE_URL=https://drupal.example.com
  ```

**Realidad Actual:**
❌ **No hay botón de copy**
❌ **Secret no es recuperable** (está encrypted en DB)
❌ **Usuario debe copiar UUID manualmente**
❌ **No genera snippet de código**

**Gap Crítico:**
❌ **El secret se genera pero no se muestra** - esto es un BLOCKER

**Solución Necesaria:**
- Mostrar secret solo UNA VEZ al crear consumer (copy-to-clipboard)
- Permitir regenerar secret (con warning)
- O usar otro approach de autenticación para desarrollo

---

## FASE 5: CONFIGURACIÓN DE CORS

### 5.1 Agregar Frontend URL a CORS

**Acción:** Alex añade `https://localhost:3000` (Next.js dev) y `https://mysite.vercel.app` (production)

**Expectativa:**
- Agregar URLs fácilmente
- Validación inline (URL válida?)
- Test button "Test CORS from URL"

**Realidad Actual:**
✅ Textarea para agregar URLs (una por línea)
✅ Validación en submit

**Gaps:**
❌ **No valida formato en tiempo real**
❌ **No detecta errores comunes** (trailing slash, http vs https)
❌ **No tiene "Test CORS" button**
❌ **No sugiere wildcard para subdominios** (*.vercel.app)
❌ **No explica implicaciones de seguridad**

---

## FASE 6: PRIMER TEST DE AUTENTICACIÓN

### 6.1 Obtener Access Token (Frontend)

**Acción:** Alex escribe código Next.js para obtener token

**Expectativa:**
- Documentación clara con código copy-paste
- Endpoint de test en Drupal para validar

**Realidad Actual:**
❌ **No hay endpoint de test**
❌ **No hay código de ejemplo en el dashboard**
❌ **Usuario debe buscar en README**

**Código que Alex debe escribir:**
```javascript
const response = await fetch('https://drupal.example.com/oauth/token', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: new URLSearchParams({
    grant_type: 'client_credentials',
    client_id: process.env.DRUPAL_CLIENT_ID,
    client_secret: process.env.DRUPAL_CLIENT_SECRET,
  }),
})

const { access_token } = await response.json()
```

**Problemas Comunes (de investigación):**

### 6.2 Error: "The client authentication failed"

**Causa:** Secret incorrecto o consumer no existe

**Debug Actual:**
❌ **No hay logging claro en Drupal**
❌ **No hay dashboard de "Failed Auth Attempts"**
❌ **Error message no es específico**

**Lo que debería haber:**
❌ Dashboard muestra: "Failed auth attempt from IP X with client_id Y at Z time"
❌ Suggestions: "Client ID not found - verify credentials"

---

### 6.3 Error: CORS Policy

**Causa:** CORS no configurado correctamente

**Debug Actual:**
```
Access to fetch at 'https://drupal.example.com/oauth/token' from origin
'http://localhost:3000' has been blocked by CORS policy
```

**Lo que debería haber:**
❌ **Test endpoint:** `/drupal-headless/cors-test?origin=http://localhost:3000`
❌ **Responde:** "CORS allowed ✓" o "CORS blocked: origin not in allowlist"
❌ **Dashboard muestra:** "Blocked CORS request from X origin at Y time"

---

## FASE 7: PRIMERA API REQUEST

### 7.1 Fetch Content via JSON:API

**Acción:** Alex hace request a `/jsonapi/node/article`

**Código:**
```javascript
const articles = await fetch('https://drupal.example.com/jsonapi/node/article', {
  headers: {
    'Authorization': `Bearer ${access_token}`,
  },
})
```

**Problemas Comunes:**

### 7.2 Error: 403 Forbidden

**Causa:** Consumer no tiene permisos para ver contenido

**Debug Actual:**
❌ **No hay explicación de qué permisos necesita el consumer**
❌ **Dashboard no muestra qué endpoints están accesibles para cada consumer**

**Lo que debería haber:**
❌ **Permissions Helper:** "Your consumer needs 'access content' permission"
❌ **Endpoint Tester:** Test `/jsonapi/node/article` con consumer X
❌ **Scope Suggester:** Based on your request, add scope Y

---

### 7.3 Estructura de Datos JSON:API (Pain Point de Investigación)

**Problema:** "Returned data structures are by default derived from drupal arrays, which converted into JSON are not very user-friendly"

**Ejemplo de respuesta:**
```json
{
  "data": [
    {
      "type": "node--article",
      "id": "uuid-here",
      "attributes": {
        "title": "Article Title",
        "body": {
          "value": "<p>Content</p>",
          "format": "basic_html",
          "processed": "<p>Content</p>"
        }
      },
      "relationships": {
        "field_image": {
          "data": {
            "type": "file--file",
            "id": "another-uuid"
          }
        }
      }
    }
  ],
  "included": [...]
}
```

**Gap Crítico:**
❌ **Drupal Headless Module no ofrece normalizers personalizados**
❌ **No hay opción "Simple JSON Output"** (sin JSON:API spec complexity)
❌ **No hay transformers** para estructuras más simples

**Lo que debería haber:**
Opción en config: "JSON Output Format"
- [ ] JSON:API Spec (default)
- [ ] Simplified (flat structure)
- [ ] Custom (define transformer)

**Ejemplo de output simplificado:**
```json
{
  "data": [
    {
      "id": "uuid",
      "title": "Article Title",
      "body": "<p>Content</p>",
      "image": {
        "url": "https://cdn.example.com/image.jpg",
        "alt": "Alt text"
      }
    }
  ]
}
```

---

## FASE 8: PREVIEW DE CONTENIDO (Pain Point Crítico)

### 8.1 Content Editor Necesita Preview

**Contexto:** Maria (content editor) crea un artículo en Drupal, quiere ver cómo se ve en el sitio Next.js ANTES de publicar.

**Expectativa (de investigación - esto es el pain point #1 de editores):**
- Botón "Preview" en Drupal que abre iframe o nueva tab
- Se ve el sitio Next.js con el contenido draft
- Funciona con content moderation (draft, review, published)

**Realidad Actual del Módulo:**
❌ **NO HAY PREVIEW SYSTEM**
❌ **NO HAY IFRAME INTEGRATION**
❌ **NO HAY NEXT.JS PREVIEW MODE SETUP**

**Gap Crítico:**
Este es el feature #1 según investigación. Without preview:
- Editores publican contenido sin ver cómo se ve
- Workflow ineficiente (publish → check → unpublish → edit → repeat)
- Frustración masiva

**Lo que debería haber:**

1. **Preview Configuration (por consumer):**
   - Preview URL template: `https://frontend.com/api/preview?secret={secret}&slug={slug}`
   - Preview button en edit form
   - Support para revisions

2. **Preview Controller:**
   ```
   /drupal-headless/preview/{entity_type}/{entity_id}
   ```

3. **Preview Token Generation:**
   - Generate temporary token (expira en 1 hora)
   - Incluye entity data en JWT
   - Frontend valida token

4. **Iframe Integration:**
   - Botón "Preview in iframe" muestra el frontend dentro de Drupal
   - Multi-device preview (desktop, tablet, mobile)

**Impacto:**
🔴 **BLOCKER MASIVO para content editors**
🔴 **Sin esto, el headless CMS no es viable para muchos clientes**

---

## FASE 9: INVALIDACIÓN DE CACHÉ (Pain Point de Investigación)

### 9.1 Contenido Actualizado en Drupal

**Contexto:** Editor publica cambio en artículo. Frontend Next.js usa ISR (Incremental Static Regeneration).

**Expectativa:**
- Drupal notifica a Next.js automáticamente
- Next.js revalida la página
- Cambio visible en segundos

**Realidad Actual:**
❌ **NO HAY WEBHOOK/NOTIFICATION SYSTEM**
❌ **Frontend no sabe que Drupal cambió**
❌ **Usuario debe revalidar manualmente** (o esperar TTL)

**Gap Crítico:**
Según investigación (Headless CMS module tiene "Notify" submodule con Webhooks)

**Lo que debería haber:**

1. **Webhook Configuration:**
   - URL de revalidation: `https://frontend.com/api/revalidate`
   - Secret para validar request
   - Eventos: entity create, update, delete, cache_rebuild

2. **Event Listeners:**
   ```php
   hook_entity_update($entity) {
     // Notify all configured consumers
   }
   ```

3. **Queue System:**
   - Para no bloquear saves en Drupal
   - Retry logic si webhook falla

4. **Notification Log:**
   - Dashboard muestra: "Sent revalidation to Next.js at X time - Status: 200 OK"

**Impacto:**
🔴 **Content no se actualiza en tiempo real**
🔴 **Experiencia de editor pobre**

---

## FASE 10: RATE LIMITING (Funcionalidad Parcial)

### 10.1 Activar Rate Limiting

**Acción:** Alex activa rate limiting: 100 requests / hora

**Expectativa:**
- Se aplique inmediatamente
- Logs de requests que exceden límite
- Whitelist para IPs confiables

**Realidad Actual:**
✅ Configuración existe en settings
❌ **NO ESTÁ IMPLEMENTADO** - solo la config

**Gap Crítico:**
El rate limiting está en config pero no hace nada. Necesita:

1. **Middleware/Event Subscriber:**
   ```php
   class RateLimitSubscriber implements EventSubscriberInterface {
     // Check rate limit per consumer/IP
   }
   ```

2. **Storage:**
   - Cache API o database table
   - Track: consumer_id, timestamp, count

3. **Response Headers:**
   ```
   X-RateLimit-Limit: 100
   X-RateLimit-Remaining: 87
   X-RateLimit-Reset: 1640000000
   ```

4. **Dashboard:**
   - Gráfica de requests per consumer
   - Alert cuando cerca del límite

**Impacto:**
🟡 **No crítico para MVP pero importante para producción**

---

## FASE 11: MULTI-SITE / MULTI-FRAMEWORK

### 11.1 Agregar Segunda App Frontend

**Contexto:** Cliente quiere:
- Next.js app (sitio público)
- React app (admin panel)
- Mobile app (React Native)

Todos desde el mismo Drupal.

**Expectativa:**
- Crear 3 consumers diferentes
- Configurar permisos por consumer
- Monitorear uso por app

**Realidad Actual:**
✅ Puede crear múltiples consumers (via service)
❌ **No hay UI dedicada para esto**
❌ **No hay dashboard comparativo**
❌ **No hay analytics per consumer**

**Gaps:**
❌ **Multi-consumer Management UI**
❌ **Per-consumer analytics** (requests, errors, popular endpoints)
❌ **Per-consumer CORS** (cada app tiene su origin)
❌ **Per-consumer rate limits**

---

## FASE 12: DEBUGGING Y TROUBLESHOOTING

### 12.1 API Request Falla

**Escenario:** Frontend hace request, obtiene error 500

**Expectativa:**
- Logs claros en Drupal
- Dashboard muestra últimos errores
- Suggest fixes

**Realidad Actual:**
❌ **No hay API error logging específico**
❌ **No hay endpoint de health check**
❌ **No hay test suite** incluido en módulo

**Lo que debería haber:**

1. **Error Log Dashboard:**
   `/admin/drupal-headless/logs`
   - Filter por consumer, endpoint, status code
   - Search por error message

2. **Health Check Endpoint:**
   ```
   GET /drupal-headless/health
   Response:
   {
     "status": "healthy",
     "checks": {
       "database": "ok",
       "jsonapi": "ok",
       "oauth": "ok",
       "private_files": "ok"
     }
   }
   ```

3. **Request Debugger:**
   - Simular request desde dashboard
   - Ver exact response
   - Headers, body, status

---

## FASE 13: PRODUCCIÓN

### 13.1 Deploy a Producción

**Acción:** Alex despliega Drupal a producción

**Checklist que debería existir:**
❌ OAuth2 keys generadas y seguras
❌ CORS configurado solo para dominios de producción
❌ Rate limiting activado
❌ Consumers con permisos mínimos necesarios
❌ Monitoring activado
❌ Backups de configuración

**Realidad:**
❌ **No hay checklist de production readiness**
❌ **No hay security audit** incluido
❌ **No hay export de configuración** específica del módulo

---

## RESUMEN DE GAPS CRÍTICOS

### 🔴 BLOCKERS (Sin estos, el módulo no cumple su propósito)

1. **OAuth2 Keys Generation**
   - Actual: Manual con OpenSSL
   - Necesario: Auto-generate o 1-click button

2. **Simple OAuth Auto-Configuration**
   - Actual: Usuario debe configurar manualmente en otro módulo
   - Necesario: Auto-configure o wizard

3. **Consumer Secret Retrieval**
   - Actual: Secret encrypted, no recuperable
   - Necesario: Show-once o regenerate option

4. **Preview System**
   - Actual: No existe
   - Necesario: Iframe preview + Next.js preview mode

5. **Consumer Management UI**
   - Actual: Debe usar módulo Consumers directamente
   - Necesario: UI integrada con .env generation

### 🟠 CRÍTICOS (Reducen mucho el valor)

6. **Cache Invalidation / Webhooks**
   - Actual: No existe
   - Necesario: Notify system para revalidation

7. **Setup Wizard**
   - Actual: Multiple forms disconnected
   - Necesario: Step-by-step wizard

8. **Rate Limiting Implementation**
   - Actual: Solo config, no funciona
   - Necesario: Implementación completa

9. **CORS Testing**
   - Actual: Solo config
   - Necesario: Test endpoint + debugging

10. **Error Logging & Debugging**
    - Actual: No existe
    - Necesario: Dashboard de errors + health check

### 🟡 IMPORTANTES (Nice to have, mejoran UX)

11. **JSON Output Simplification**
12. **API Documentation Generator**
13. **Multi-consumer Analytics**
14. **Production Readiness Checklist**
15. **Environment Presets** (dev, staging, prod)

---

## MÉTRICAS DE ÉXITO DEL JOURNEY

**Time to First API Call (Ideal):**
- Instalar módulo: 2 min
- Wizard setup: 5 min
- Crear consumer + copiar credentials: 2 min
- Configurar frontend: 3 min
- First successful API call: **12 minutos total**

**Time to First API Call (Realidad Actual):**
- Instalar módulo: 5 min
- Configurar private files: 10 min
- Generar OAuth2 keys manualmente: 15 min
- Configurar Simple OAuth: 10 min
- Crear consumer manualmente: 5 min
- Buscar cómo obtener secret: 20 min (frustración)
- Configurar CORS: 5 min
- Debug auth errors: 30 min
- **Total: 100 minutos** ❌

---

## CONCLUSIÓN

El módulo actual implementa la **estructura base y configuración**, pero le faltan **todos los features que realmente eliminan los pain points** identificados en la investigación.

Es un buen foundation, pero no es usable en producción sin implementar los features críticos marcados en rojo.

**Siguiente Paso Recomendado:**
Implementar en orden de prioridad:
1. OAuth2 key generation
2. Consumer management UI con secret handling
3. Setup wizard
4. Preview system
5. Webhook/notifications
