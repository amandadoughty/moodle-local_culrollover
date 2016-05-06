/*
 * @package    local_culrollover
 * @copyright  2016 Amanda Doughty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 /**
  * @module local_culrollover/defaultoptions
  */

define(['jquery', 'local_culrollover/datatables'], function($) {
    return {
        initialise: function () {
            // see http://legacy.datatables.net/usage/columns for documentation
            var oTable = $('#previous').dataTable({
                "aoColumns": [
                    { "bVisible": false},
                    { "iDataSort": 0},
                    null,
                    null,
                    null,
                    null,
                    null,
                    { "bSortable": false }
                ]
            });
            oTable.fnSort( [0, 'desc'] );
        }
    };
});