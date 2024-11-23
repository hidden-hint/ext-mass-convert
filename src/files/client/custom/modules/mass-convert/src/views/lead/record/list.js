Espo.define('mass-convert:views/lead/record/list', 'views/record/list', function (Dep) {
    return Dep.extend({
        setup: function() {
            Dep.prototype.setup.call(this);

            this.massActionList.push('convert');
            this.checkAllResultMassActionList.push('convert');
        },
        massActionConvert: function () {
            Espo.Ui.notify(this.translate('converting', 'messages'));

            const data = {ids: this.checkedList};

            Espo.Ajax.postRequest(this.collection.entityType + '/action/massConvert', data).then(() => {
                Espo.Ui.notify(false);

                this.listenToOnce(this.collection, 'sync', () => {
                    data.ids.forEach(id => {
                        if (this.collection.get(id)) {
                            this.uncheckRecord(id);
                        }
                    });
                });

                this.collection.fetch();
            });
        },
    });
});
