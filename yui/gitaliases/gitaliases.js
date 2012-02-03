/**
 * YUI module for Git aliases admin page in the Developers plugin
 *
 * @author  David Mudrak <david@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
YUI.add('moodle-local_dev-gitaliases', function(Y) {

    var GITALIASES = function() {
        GITALIASES.superclass.constructor.apply(this, arguments);
    }

    Y.extend(GITALIASES, Y.Base, {

        initializer : function(config) {
            Y.one('#aliaseseditor').delegate('focus', this.searchbox_focused, 'input.aliasdata-search');
        },

        searchbox_focused : function (e) {
            var searchbox = e.currentTarget;
            var userid = searchbox.ancestor('div').one('.aliasdata-userid');
            var icon = searchbox.ancestor('div').one('.aliasdata-icon');
            if (!searchbox.ac) {
                // we are focusing the searchbox for the first time, let us activate autocomplete in it
                searchbox.plug(Y.Plugin.AutoComplete, {
                    minQueryLength : 3,
                    queryDelay : 500,
                    resultListLocator: 'results',
                    resultTextLocator: 'signature',
                    resultHighlighter: 'phraseMatch',
                    source : M.cfg.wwwroot + '/local/dev/admin/git-aliases-search.php?sesskey=' + M.cfg.sesskey + '&q={query}',
                    on : {
                        query : function (e) {
                            icon.setContent('<img src="' + M.util.image_url('i/loading_small', 'core') + '" />');
                        },
                        select : function(e) {
                            var selected = e.result.raw;
                            console.log(selected);
                            userid.set('value', selected.userid);
                        },
                        clear : function (e) {
                            icon.setContent('');
                        },
                        results : function (e) {
                            icon.setContent('');
                        }
                    }
                });
            }
        },

    }, {
        NAME : 'gitaliases',
        ATTRS : { }
    });

    M.local_dev = M.local_dev || {};

    M.local_dev.init_gitaliases = function(config) {
        M.local_dev.GITALIASES = new GITALIASES(config);
    }

}, '@VERSION@', { requires:['autocomplete', 'autocomplete-highlighters', 'escape'] });
