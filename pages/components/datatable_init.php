<?php
function initDataTable($id) {
    ?>
    <style>
        #<?= $id ?>_wrapper .page-item.active .page-link {
            background-color: #FFD700 !important; /* Yellow */
            border-color: #FFD700 !important;
            color: #000000 !important; /* Black text for contrast */
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(255, 215, 0, 0.2);
        }
        #<?= $id ?>_wrapper .page-link {
            color: #000000;
        }
        #<?= $id ?>_wrapper .page-link:hover {
            background-color: #fffbef;
            color: #ccac00;
        }
        .dataTables_filter input:focus {
            border: 1px solid #FFD700 !important;
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25) !important;
            outline: none;
        }
        .dataTables_length select:focus {
            border-color: #FFD700 !important;
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25) !important;
        }
    </style>

    <script>
    $(document).ready(function() {
        if ($.fn.DataTable) {
            if (!$.fn.DataTable.isDataTable('#<?= $id ?>')) {
                $('#<?= $id ?>').DataTable({
                    "pageLength": 10,
                    "ordering": true,
                    "responsive": true,
                    "language": {
                        "search": "",
                        "searchPlaceholder": "Real-time search...",
                        "paginate": {
                            "previous": "Prev",
                            "next": "Next"
                        }
                    },
                    "dom": '<"d-flex justify-content-between align-items-center mt-3 px-3 mb-3"lf>rt<"d-flex justify-content-between align-items-center px-3 mt-3"ip>',
                    "drawCallback": function() {
                        $('#<?= $id ?> .dataTables_empty').addClass('text-center py-3 text-muted fw-medium');
                        $('#<?= $id ?>_paginate .pagination').addClass('pagination-sm');
                    }
                });

                $('.dataTables_filter input').addClass('form-control shadow-sm border bg-white px-3').css({
                    'border-radius': '10px',
                    'border-color': '#e2e8f0'
                });
                
                $('.dataTables_length select').addClass('form-select shadow-sm border').css({
                    'border-radius': '8px',
                    'width': 'auto',
                    'display': 'inline-block',
                    'border-color': '#e2e8f0'
                });
            }
        } else {
            console.error("DataTable library not loaded.");
        }
    });
    </script>
    <?php
}
?>