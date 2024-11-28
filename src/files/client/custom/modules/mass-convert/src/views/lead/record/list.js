Espo.define('mass-convert:views/lead/record/list', 'views/record/list', function (Dep) {
    return Dep.extend({
        setup: function() {
            Dep.prototype.setup.call(this);

            this.massActionList.push('convert');
            this.checkAllResultMassActionList.push('convert');
        },
        massActionConvert: function () {
            Espo.Ui.notify(this.translate('converting', 'messages'));

            Espo.Ajax.postRequest(
                this.collection.entityType + '/action/massConvert',
                this.getMassActionSelectionPostData()
            ).then(() => {
                Espo.Ui.notify(false);

                this.listenToOnce(this.collection, 'sync', () => {
                    this.checkedList.forEach(id => {
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
