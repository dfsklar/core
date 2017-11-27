import UserPage from 'flarum/components/UserPage';
import LoadingIndicator from 'flarum/components/LoadingIndicator';
import Button from 'flarum/components/Button';
import CommentPost from 'flarum/components/CommentPost';

export default class GroupRosterPage extends UserPage {
  init() {
    super.init();

    /**
     * Whether or not the activity feed is currently loading.
     *
     * @type {Boolean}
     */
    this.loading = true;

    // LOAD THE GROUP // unfortunately this has NO relationship information!
    this.group = app.store.getBy('groups', 'slug', m.route.param('groupid'));
    // !!! :-(  unfortunately the returned group record has NO relationship information!
    // But the indx ID is:  this.group.data.id

    // DFSKLARD: USE THE API TO GET THE FULL DATA ABOUT THIS GROUP.  app.store.find() is how you call the API from JS.
    app.store.find('groups', this.group.data.id)
    .then(this.show.bind(this));

    m.lazyRedraw();
  }


  view() {
    return (
      <div>
      <h1>HELLO FROM group roster page </h1>
      <p>This page already has the data in the GroupRosterPage JS-side class, so just a template away from rendering
        with no interaction yet.</p></div>
    );
  }

  /**
   * Initialize the component to display the given discussion.
   *
   * @param {Group} group
   */
  show(group) {
    /*
    group.payload.included is an array of all users:
       each user record has:
          .attributes.username
                      .uid < the user's formed-side UUID
    */
    console.log("WE HAVE MADE IT");
  }

}
