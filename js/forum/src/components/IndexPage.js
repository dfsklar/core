import { extend } from 'flarum/extend';
import Page from 'flarum/components/Page';
import ItemList from 'flarum/utils/ItemList';
import listItems from 'flarum/helpers/listItems';
import icon from 'flarum/helpers/icon';
import DiscussionList from 'flarum/components/DiscussionList';
import WelcomeHero from 'flarum/components/WelcomeHero';
import DiscussionComposer from 'flarum/components/DiscussionComposer';
import LogInModal from 'flarum/components/LogInModal';
import DiscussionPage from 'flarum/components/DiscussionPage';
import Dropdown from 'flarum/components/Dropdown';
import Button from 'flarum/components/Button';
import LinkButton from 'flarum/components/LinkButton';
import SelectDropdown from 'flarum/components/SelectDropdown';
import SiteSpecifics from 'flarum/SITESPECIFICS';


/**
 * The `IndexPage` component displays the index page, including the welcome
 * hero, the sidebar, and the discussion list.
 */
export default class IndexPage extends Page {





  refreshGroupMembershipInfo() {
    // So now you want to obtain the USER object for the currently logged-in user.
    // In that user object you'll find:
    //   data.relationships.groups.data which is an array.
    //     Each record in that array has a "id" object, string repr of a number.
    // The current user's ID is in:  app.data.session.userId
    this.loading = false;
    this.loggedinUserMembershipList = app.session.user.data.relationships.groups.data;
    this.isMemberOfGroup = this.loggedinUserMembershipList.some(group => (group.id == this.matchingGroup.data.id));
    m.redraw();
  }




  init() {
    super.init();

    // If the user is returning from a discussion page, then take note of which
    // discussion they have just visited. After the view is rendered, we will
    // scroll down so that this discussion is in view.
    if (app.previous instanceof DiscussionPage) {
      this.lastDiscussion = app.previous.discussion;
    }

    // If the user is coming from the discussion list, then they have either
    // just switched one of the parameters (filter, sort, search) or they
    // probably want to refresh the results. We will clear the discussion list
    // cache so that results are reloaded.
    if (app.previous instanceof IndexPage) {
      app.cache.discussionList = null;
    }

    const params = this.params();


    // DFSKLARD: experiment with catching a situation requiring re-routing early.
    // If this is a display of a GROUP (i.e. top-level tag), reroute to
    // one of its SESSIONS (secondary-level tag).
    if (!(params.tags)) {
      // If no tags at all, the user hasn't even chosen a GROUP to dive into.
      // We are thus going to lead them to the list of groups!
      m.route(app.route('tags'));
      return;
    }
    else {
      this.current_tag = app.store.getBy('tags', 'slug', params.tags);
      if (this.current_tag) {
        if ( ! (this.current_tag.data.attributes.isChild)) {
          // SO: we have a situation where we want to reroute to the "latest-added"
          // subchild of this tag.
          // How to find subtags?
          const children = app.store.all('tags').filter(child => child.parent() === this.current_tag);          
          // 
          if (children) {
            if (children.length > 0) {
              const latest_child = children[children.length-1];
              const target = app.route.tag(latest_child);
              m.route(target);
              return;
            }
          }
        }
      }
    }


    // Obtain full info about the group that is associated with this primary tag.
    this.associatedGroupSLUG = this.current_tag.parent().slug();
    this.matchingGroup = app.store.getBy('groups','slug', this.associatedGroupSLUG);
    let associatedGroupID = this.matchingGroup.id();


    app.store.find('groups', associatedGroupID)
      .then(this.handleGroupDetails.bind(this));


    app.store.find('users', app.session.user.id())
      .then(this.refreshGroupMembershipInfo.bind(this));


    if (app.cache.discussionList) {
      // Compare the requested parameters (sort, search query) to the ones that
      // are currently present in the cached discussion list. If they differ, we
      // will clear the cache and set up a new discussion list component with
      // the new parameters.
      Object.keys(params).some(key => {
        if (app.cache.discussionList.props.params[key] !== params[key]) {
          app.cache.discussionList = null;
          return true;
        }
      });
    }

    if (!app.cache.discussionList) {
      let thisDiscussionContextParams = {
        filter: undefined,
        q: undefined,
        sort: undefined,
        tags: this.current_tag.data.attributes.slug
      };
      app.cache.discussionList = new DiscussionList({params: thisDiscussionContextParams});
    }

    // DFSKLARD bizarre
    app.cache.discussionList.parentIndexPage = this;

    app.history.push('index', app.translator.trans('core.forum.header.back_to_index_tooltip'));

    this.bodyClass = 'App--index';
  }


  recordGroupRoster(r) {
    this.groupMembershipRoster = r.data.relationships.users.data;
    m.redraw();
  }





  handleGroupDetails(group) {
      /*
      group.payload.included is an array of all users:
         each user record has:
            .attributes.username
                        .uid < the user's formed-side UUID
      */
      console.log("WE HAVE OBTAINED GROUP INFO");
  }


  onunload() {
    // Save the scroll position so we can restore it when we return to the
    // discussion list.
    app.cache.scrollTop = $(window).scrollTop();
  }

  view() {
    const canStartDiscussion = app.forum.attribute('canStartDiscussion') || !app.session.user;
    return (
    <div className="IndexPage-Supercontainer">
      <div className="IndexPage-FormFactorNotSupported">
        Sorry but this page does not yet support narrow-width form factors.
        Use a laptop/desktop computer and use a wide screen width to expose
        this page's UX.
      </div>
      <div className="IndexPage">
        {this.hero()}
        <div className="container">
          <div className="IndexPage-results sideNavOffset">
            <div className="hidden-forever">
               ** THIS IS THE GREY BOX FROM FORMED.ORG UI SPEC, containing
               ** the full name of the current session *AND* the POST button.
            </div>
            <div className="IndexPage-results-header IndexPage-results-child">
              <div className="session-name">
                 {this.current_tag.data.attributes.name}
                 <div className="literal-discussion">Discussion</div>
              </div>
              { this.isMemberOfGroup ? (
                <div className="button-create-new-discussion">
                {Button.component({
                  children:  [ <span>NEW POST</span> ],
                  icon: 'edit',
                  className: 'Button Button--primary IndexPage-newDiscussion',
                  itemClassName: 'App-primaryControl',
                  onclick: this.newDiscussion.bind(this),
                  disabled: !canStartDiscussion
                })}</div> ) : '' 
              }
            </div>
          </div>
          <div className="IndexPage-results-body">
            <div className="IndexPage-results-child">
              {app.cache.discussionList.render()}
            </div>
          </div>
        </div>
      </div>
    </div>
    );
  }

  config(isInitialized, context) {
    super.config(...arguments);

    if (isInitialized) return;

    extend(context, 'onunload', () => $('#app').css('min-height', ''));

    app.setTitle('');
    app.setTitleCount(0);

    // DFSKLARD: Create a button for returning to formed.org parish homepage
    const $destination = $('#header-navigation');
    const destURL = app.siteSpecifics.fetchFormedURL() + "/home?linkId=custom-content";
    $destination.empty();
    $destination.append('<a href="' + destURL + '" class=returntoformed>&lt; RETURN TO COMMUNITY</a>');

    // Work out the difference between the height of this hero and that of the
    // previous hero. Maintain the same scroll position relative to the bottom
    // of the hero so that the sidebar doesn't jump around.
    const oldHeroHeight = app.cache.heroHeight;
    const heroHeight = app.cache.heroHeight = this.$('.Hero').outerHeight();
    const scrollTop = app.cache.scrollTop;

    $('#app').css('min-height', $(window).height() + heroHeight);

    // Scroll to the remembered position. We do this after a short delay so that
    // it happens after the browser has done its own "back button" scrolling,
    // which isn't right. https://github.com/flarum/core/issues/835
    const scroll = () => $(window).scrollTop(scrollTop - oldHeroHeight + heroHeight);
    scroll();
    setTimeout(scroll, 1);

    // If we've just returned from a discussion page, then the constructor will
    // have set the `lastDiscussion` property. If this is the case, we want to
    // scroll down to that discussion so that it's in view.
    if (this.lastDiscussion) {
      const $discussion = this.$(`.DiscussionListItem[data-id="${this.lastDiscussion.id()}"]`);

      if ($discussion.length) {
        const indexTop = $('#header').outerHeight();
        const indexBottom = $(window).height();
        const discussionTop = $discussion.offset().top;
        const discussionBottom = discussionTop + $discussion.outerHeight();

        if (discussionTop < scrollTop + indexTop || discussionBottom > scrollTop + indexBottom) {
          $(window).scrollTop(discussionTop - indexTop);
        }
      }
    }
  }

  /**
   * Get the component to display as the hero.
   *
   * @return {MithrilComponent}
   */
  hero() {
    return WelcomeHero.component();
  }

  /**
   * Build an item list for the sidebar of the index page. By default this is a
   * "New Discussion" button, and then a DropdownSelect component containing a
   * list of navigation items.
   *
   * @return {ItemList}
   */
  sidebarItems() {
    const items = new ItemList();

    items.add('nav',
      SelectDropdown.component({
        children: this.navItems(this).toArray(),
        buttonClassName: 'Button',
        className: 'App-titleControl'
      })
    );

    return items;
  }

  /**
   * Build an item list for the navigation in the sidebar of the index page.
   * Formed.org app does not use this! (DFSKLARD)
   * 
   * @return {ItemList}
   */
  navItems() {
    const items = new ItemList();
    return items;
  }

  /**
   * Build an item list for the part of the toolbar which is concerned with how
   * the results are displayed. By default this is just a select box to change
   * the way discussions are sorted.
   * 
   * DFSKLARD:  In formed.org app, we do not use this.
   *
   * @return {ItemList}
   */
  viewItems() {
    const items = new ItemList();
    return items;
  }

  /**
   * Build an item list for the part of the toolbar which is about taking action
   * on the results. By default this is just a "mark all as read" button.
   *
   * @return {ItemList}
   */
  actionItems() {
    const items = new ItemList();

    items.add('refresh',
      Button.component({
        title: app.translator.trans('core.forum.index.refresh_tooltip'),
        icon: 'refresh',
        className: 'Button Button--icon',
        onclick: () => {
          app.cache.discussionList.refresh();
          if (app.session.user) {
            app.store.find('users', app.session.user.id());
            m.redraw();
          }
        }
      })
    );

    if (app.session.user) {
      items.add('markAllAsRead',
        Button.component({
          title: app.translator.trans('core.forum.index.mark_all_as_read_tooltip'),
          icon: 'check',
          className: 'Button Button--icon',
          onclick: this.markAllAsRead.bind(this)
        })
      );
    }

    return items;
  }

  /**
   * Return the current search query, if any. This is implemented to activate
   * the search box in the header.
   *
   * @see Search
   * @return {String}
   */
  searching() {
    return this.params().q;
  }

  /**
   * Redirect to the index page without a search filter. This is called when the
   * 'x' is clicked in the search box in the header.
   *
   * @see Search
   */
  clearSearch() {
    const params = this.params();
    delete params.q;

    m.route(app.route(this.props.routeName, params));
  }

  /**
   * Redirect to the index page using the given sort parameter.
   *
   * @param {String} sort
   */
  changeSort(sort) {
    const params = this.params();

    if (sort === Object.keys(app.cache.discussionList.sortMap())[0]) {
      delete params.sort;
    } else {
      params.sort = sort;
    }

    m.route(app.route(this.props.routeName, params));
  }

  /**
   * Get URL parameters that stick between filter changes.
   *
   * @return {Object}
   */
  stickyParams() {
    return {
      sort: m.route.param('sort'),
      q: m.route.param('q')
    };
  }

  /**
   * Get parameters to pass to the DiscussionList component.
   *
   * @return {Object}
   */
  params() {
    const params = this.stickyParams();

    params.filter = m.route.param('filter');

    return params;
  }

  /**
   * Log the user in and then open the composer for a new discussion.
   *
   * @return {Promise}
   */
  newDiscussion() {
    const deferred = m.deferred();

    if (app.session.user) {
      this.composeNewDiscussion(deferred);
    } else {
      app.modal.show(
        new LogInModal({
          onlogin: this.composeNewDiscussion.bind(this, deferred)
        })
      );
    }

    return deferred.promise;
  }

  /**
   * Initialize the composer for a new discussion.
   *
   * @param {Deferred} deferred
   * @return {Promise}
   */
  composeNewDiscussion(deferred) {
    const component = new DiscussionComposer({user: app.session.user});

    app.composer.load(component);
    app.composer.show();

    deferred.resolve(component);

    return deferred.promise;
  }

  /**
   * Mark all discussions as read.
   *
   * @return void
   */
  markAllAsRead() {
    const confirmation = confirm(app.translator.trans('core.forum.index.mark_all_as_read_confirmation'));

    if (confirmation) {
      app.session.user.save({readTime: new Date()});
    }
  }
}
