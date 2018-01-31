import avatar from 'flarum/helpers/avatar';
import username from 'flarum/helpers/username';
import Dropdown from 'flarum/components/Dropdown';
import Button from 'flarum/components/Button';
import ItemList from 'flarum/utils/ItemList';
import icon from 'flarum/helpers/icon';


/**
 * This shows a button with the roster's size (number of users)
 * with a dropdown of actual user names.
 */
export default class UserRosterDropdown extends Dropdown {

  static initProps(props) {
    super.initProps(props);

    props.className = 'UserRosterDropdown';
    props.buttonClassName = 'Button Dropdown-toggle';
    props.menuClassName = 'Dropdown-menu dropdown-menu';
  }

  view() {
    this.props.children = this.items().toArray();

    return super.view();
  }

  getButtonContent() {
    const user = app.session.user;
    const suffix = (this.props.userList.length==1 ? ' member' : ' members');
    return [
      <span className="Button-label">
         {icon('user')}&thinsp;
         {icon('user')}&thinsp;
         {icon('user')}
      </span>
    ];
  }

  /**
   * Build an item list for the contents of the dropdown menu.
   *
   * @return {ItemList}
   */
  items() {
    const items = new ItemList();

    this.props.userList.forEach(function(val){
      const userinfo = app.store.getById('users',val.id);
      items.add(val.id,
        Button.component({
          children: [ userinfo.data.attributes.username ]
       }),
       -100
    );
    });

    return items;
  }
}
