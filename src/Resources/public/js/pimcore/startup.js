pimcore.registerNS("pimcore.plugin.BasilicomPathFormatterBundle");

pimcore.plugin.BasilicomPathFormatterBundle = Class.create(pimcore.plugin.admin, {
    getClassName: function () {
        return "pimcore.plugin.BasilicomPathFormatterBundle";
    },

    initialize: function () {
        pimcore.plugin.broker.registerPlugin(this);
    },

    pimcoreReady: function (params, broker) {
        // alert("BasilicomPathFormatterBundle ready!");
    }
});

// todo: can we prefill the pahformatter field?

var BasilicomPathFormatterBundlePlugin = new pimcore.plugin.BasilicomPathFormatterBundle();
