dojo.provide("custom.ComboBoxReadStore");

dojo.require("dojox.data.QueryReadStore");

dojo.declare("custom.ComboBoxReadStore",dojox.data.QueryReadStore, { 
    fetch:function(request) { 
       return this.inherited("fetch",arguments);
    }

});
