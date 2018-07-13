dojo.provide('custom.LoadingMsgFilteringSelect');
dojo.require('dijit.form.FilteringSelect');

dojo.declare('custom.LoadingMsgFilteringSelect', [dijit.form.FilteringSelect], {       
        _startSearch:function(key) {
                var spinner = dojo.create("img", { src:"/var/www/htdocs/dojo/1.6.1/custom/loading.gif" }, this.domNode, "after");
                var parent = dojo.hitch(this, "inherited", arguments);
                var request = this.store.fetch({
                        onComplete: function(){
                                parent(); 
                                dojo.destroy(spinner);
                        }                     
                });       
        }
});
