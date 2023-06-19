jQuery(document).ready(function($) {
  if (window.wpApiShare.url_path.startsWith('metrics/combined/genmap')) {
    project_combined_genmap()
  }

  function project_combined_genmap() {
    "use strict";
    let chart = jQuery('#chart')
    let spinner = ' <span class="loading-spinner active"></span> '

    chart.empty().html(spinner)
    jQuery('#metrics-sidemenu').foundation('down', jQuery('#combined-menu'));

    let translations = dtMetricsProject.translations

    chart.empty().html(`
          <div class="grid-x grid-padding-x">
              <div class="cell medium-6">
                  <span>
                    <select id="select_type" style="width: 200px;">
                      <option value="groups_to_groups" data-post-type="groups">Groups</option>
                      <option value="contacts_to_contacts" data-post-type="contacts">Coaching</option>
                      <option value="baptizer_to_baptized" data-post-type="contacts">Baptisms</option>
                    </select>
                  </span>
                  <span>
                    <i class="fi-loop" onclick="window.load_genmap()" style="font-size: 1.5em; padding:.5em;cursor:pointer;"></i>
                  </span>
              </div>
              <div class="cell medium-6" >
                <h2 style="float:right;">${window.lodash.escape(translations.title)}</h2>
              </div>
          </div>
          <hr>
          <div class="grid-x grid-padding-x">
              <div class="cell medium-9">
                <div id="genmap" style="width: 100%; border: 1px solid lightgrey; overflow:scroll;"></div>
              </div>
              <div class="cell medium-3">
                <div id="genmap-details"></div>
              </div>

           <div id="modal" class="reveal" data-reveal></div>
       `)

    window.load_genmap = () => {
      jQuery('#genmap-details').empty()
      let select_type = jQuery('#select_type')
      let p2p_type = select_type.val()
      let selected_option = select_type.find('option:selected')
      let post_type = selected_option.data('post-type')
      if ( ! p2p_type ) {
        p2p_type = 'groups_to_groups'
      }
      if ( ! post_type ) {
        post_type = 'groups'
      }
      makeRequest('POST', 'metrics/combined/genmap', { "p2p_type": p2p_type, "post_type": post_type } )
        .then(response => {
          console.log(response)
          let container = jQuery('#genmap')
          container.empty()

          var nodeTemplate = function(data) {
            return `
            <div class="title" data-item-id="${data.id}">${data.name}</div>
            <div class="content">${data.content}</div>
          `;
          };

          container.orgchart({
            'data': response,
            'nodeContent': 'content',
            'direction': 'l2r',
            'nodeTemplate': nodeTemplate,
          });

          let container_height = window.innerHeight - 200 // because it is rotated
          container.height(container_height)

          container.off('click', '.node' )
          container.on('click', '.node', function () {
            let node = jQuery(this)
            let node_id = node.attr('id')
            open_modal_details(node_id, post_type)
          })

        })
    }
    window.load_genmap()

    jQuery('#select_type').on('change', function() {
      window.load_genmap()
    })

  }

  function open_modal_details( id, post_type ) {

    let spinner = ' <span class="loading-spinner active"></span> '
    jQuery('#genmap-details').html(spinner)

    makeRequest('GET', post_type + '/' + id, null, 'dt-posts/v2/')
      .then(data => {
        console.log(data)
        let container = jQuery('#genmap-details')
        container.empty()
        if (data) {
          container.html(window.detail_template(post_type, data))
        }
      })
  }

  window.detail_template = ( post_type, data ) => {
    if ( post_type === 'contacts' ) {

      let assign_to = 0
      if ( typeof data.assigned_to !== 'undefined' ) {
        assign_to = data.assigned_to.display
      }
      let coach_list = '<ul>'
      if ( typeof data.coached_by !== 'undefined' ) {
        coach_list = '<ul>'
        jQuery.each( data.coached_by, function( index, value ) {
          coach_list += '<li>' + value['post_title'] + '</li>'
        })
        coach_list += '</ul>'
      }
      let group_list = '<ul>'
      if ( typeof data.groups !== 'undefined' ) {
        group_list = '<ul>'
        jQuery.each( data.groups, function( index, value ) {
          group_list += '<li>' + value['post_title'] + '</li>'
        })
        group_list += '</ul>'
      }
      return `
        <div class="grid-x grid-padding-x">
          <div class="cell">
            <h2>${data.title}</h2><hr>
          </div>
          <div class="cell">
            Status: ${data.overall_status['label']}
          </div>
          <div class="cell">
            Groups:
            ${group_list}
          </div>
          <div class="cell">
            Assigned To:
            ${assign_to}
          </div>
          <div class="cell">
            Coaches: <br>
            ${coach_list}
          </div>
          <div class="cell"><hr>
            <a href="${dtMetricsProject.site_url}/${post_type}/${data.ID}" target="_blank" class="button">View Contact</a>
          </div>
        </div>
      `
    } else if ( post_type === 'groups' ) {

      let members_count = 0
      if ( typeof data.member_count !== 'undefined' ) {
        members_count = data.member_count
      }
      let assign_to = 0
      if ( typeof data.assigned_to !== 'undefined' ) {
        assign_to = data.assigned_to.display
      }

      let member_list = '<ul>'
      if ( typeof data.members !== 'undefined' ) {
        member_list = '<ul>'
        jQuery.each( data.members, function( index, value ) {
          member_list += '<li>' + value['post_title'] + '</li>'
        })
        member_list += '</ul>'
      }
      let coach_list = '<ul>'
      if ( typeof data.coached_by !== 'undefined' ) {
        coach_list = '<ul>'
        jQuery.each( data.coached_by, function( index, value ) {
          coach_list += '<li>' + value['post_title'] + '</li>'
        })
        coach_list += '</ul>'
      }

      return `
        <div class="grid-x grid-padding-x">
          <div class="cell">
            <h2>${data.title}</h2><hr>
          </div>
          <div class="cell">
            Type: ${data.group_status['label']}
          </div>
          <div class="cell">
            Type: ${data.group_type['label']}
          </div>
          <div class="cell">
            Member Count: ${members_count}
          </div>
          <div class="cell">
            Members: <br>
            ${member_list}
          </div>
          <div class="cell">
            Assigned To:
            ${assign_to}
          </div>
          <div class="cell">
            Coaches: <br>
            ${coach_list}
          </div>
          <div class="cell"><hr>
            <a href="${dtMetricsProject.site_url}/${post_type}/${data.ID}" target="_blank" class="button">View Group</a>
          </div>
        </div>
      `
    }
  }

})
// {
//   'icons': {
//   'theme': 'oci',
//     'parentNode': 'oci-menu',
//     'expandToUp': 'oci-chevron-up',
//     'collapseToDown': 'oci-chevron-down',
//     'collapseToLeft': 'oci-chevron-left',
//     'expandToRight': 'oci-chevron-right',
//     'collapsed': 'oci-plus-square',
//     'expanded': 'oci-minus-square',
//     'spinner': 'oci-spinner'
// },
//   'nodeTitle': 'name',
//   'nodeId': 'id',
//   'toggleSiblingsResp': false,
//   'visibleLevel': 999,
//   'chartClass': '',
//   'exportButton': false,
//   'exportButtonName': 'Export',
//   'exportFilename': 'OrgChart',
//   'exportFileextension': 'png',
//   'draggable': false,
//   'direction': 't2b',
//   'pan': false,
//   'zoom': false,
//   'zoominLimit': 7,
//   'zoomoutLimit': 0.5
// };
