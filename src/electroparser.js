

$(document).ready(function () {

    $('#savemarkups').on('click', function() {
        $('#tree').treeview('expandAll', { silent: true });
    })

    $('.catline').on('click', function(e) {
        e.preventDefault();
    })



    let id = 1000;

    var Main = {
        data() {
            const data = [{
                id: 1,
                label: 'Level one 1',
                children: [{
                    id: 4,
                    label: 'Level two 1-1',
                    children: [{
                        id: 9,
                        label: 'Level three 1-1-1'
                    }, {
                        id: 10,
                        label: 'Level three 1-1-2'
                    }]
                }]
            }, {
                id: 2,
                label: 'Level one 2',
                children: [{
                    id: 5,
                    label: 'Level two 2-1'
                }, {
                    id: 6,
                    label: 'Level two 2-2'
                }]
            }, {
                id: 3,
                label: 'Level one 3',
                children: [{
                    id: 7,
                    label: 'Level two 3-1'
                }, {
                    id: 8,
                    label: 'Level two 3-2'
                }]
            }];
            return {
                data4: JSON.parse(JSON.stringify(data)),
                data5: JSON.parse(JSON.stringify(data))
            }
        },

        methods: {
            append(data) {
                const newChild = { id: id++, label: 'testtest', children: [] };
                if (!data.children) {
                    this.$set(data, 'children', []);
                }
                data.children.push(newChild);
            },

            remove(node, data) {
                const parent = node.parent;
                const children = parent.data.children || parent.data;
                const index = children.findIndex(d => d.id === data.id);
                children.splice(index, 1);
            },

            renderContent(h, { node, data, store }) {
                return (
                    <span class="custom-tree-node">
            <span>{node.label}</span>
            <span>
              <el-button size="mini" type="text" on-click={ () => this.append(data) }>Append</el-button>
              <el-button size="mini" type="text" on-click={ () => this.remove(node, data) }>Delete</el-button>
            </span>
          </span>);
            }
        }
    };
    var Ctor = Vue.extend(Main)
    new Ctor().$mount('#app')
});


