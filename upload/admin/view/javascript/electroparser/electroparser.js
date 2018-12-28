

$(document).ready(function () {

    $('#savemarkups').on('click', function() {
        $('#tree').treeview('expandAll', { silent: true });
    })

    $('.catline').on('click', function(e) {
        e.preventDefault();
    })

});


