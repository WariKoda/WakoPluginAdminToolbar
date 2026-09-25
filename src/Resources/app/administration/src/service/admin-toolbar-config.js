const CONFIG_DOMAIN = 'WakoPluginAdminToolbar.config';

let pendingConfig = null;

// Shared by all page header buttons, so one Administration session reads the global config once.
// The config help texts ask admins to reload the Administration after changing these settings.
export function loadAdminToolbarConfig(systemConfigApiService) {
    if (!pendingConfig) {
        pendingConfig = systemConfigApiService.getValues(CONFIG_DOMAIN).catch((error) => {
            pendingConfig = null;
            throw error;
        });
    }

    return pendingConfig;
}
