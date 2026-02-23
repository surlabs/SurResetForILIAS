# Configuracion de Cron en ILIAS 9

Este documento describe como activar la ejecucion periodica de cronjobs en ILIAS 9, sin depender de componentes adicionales.

## 1. Usuario tecnico para cron en ILIAS

1. Crea un usuario en ILIAS para ejecutar el cron.
2. No requiere permisos especiales.
3. Guarda:
   - `USUARIO_ILIAS`
   - `CLIENT_ID`

## 2. Configuracion desde Plesk (Scheduled Task)

1. En Plesk, entra al dominio de ILIAS.
2. Abre `Scheduled Tasks`.
3. Crea una nueva tarea con estos valores:
   - `Task Type`: `Run a PHP script`
   - `Script path`: `<ILIAS_DOCUMENT_ROOT>/cron/cron.php`
   - `Arguments`: `run-jobs USUARIO_ILIAS CLIENT_ID`
   - `PHP Version`: la misma version que usa ILIAS
   - `Run`: `* * * * *` (cada minuto)
4. Guarda la tarea.

## 3. Configuracion por linea de comandos (Linux)

1. Edita el crontab del usuario de sistema que ejecuta ILIAS:

```bash
sudo crontab -u system_user -e
```

2. Agrega esta linea:

```bash
* * * * * /usr/bin/php -f '/path/RootPathILIAS/cron/cron.php' -- 'run-jobs' 'USUARIO_ILIAS' 'CLIENT_ID' >> $HOME/ilias-cron.log 2>&1
```

## 4. Ejecucion manual inicial

```bash
sudo -u system_user /usr/bin/php '/path/RootPathILIAS/cron/cron.php' -- 'run-jobs' 'USUARIO_ILIAS' 'CLIENT_ID'
```

## 5. Comprobaciones recomendadas

1. Verifica que la ruta `cron/cron.php` sea correcta.
2. Verifica la version de PHP activa:

```bash
php -v
```

3. Confirma que el `CLIENT_ID` coincide con el cliente configurado en ILIAS.
