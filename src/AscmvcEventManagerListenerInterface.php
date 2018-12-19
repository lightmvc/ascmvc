<?php
/**
 * ASC LightMVC
 *
 * @package    ASC LightMVC
 * @author     Andrew Caya
 * @link       https://github.com/andrewscaya
 * @version    1.0.0
 * @license    http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Ascmvc;


/**
 * EventManagerListenerInterface allows the implementing class
 * to be consumed as a AscmvcEventManager class listener.
 * 
 * The interface's methods correspond exactly to the
 * App Class' runlevels as they are defined in its run() method
 * so that, in turn, these methods may be dynamically called by the
 * EventManager's event-driven triggerEvent() method.
 */
interface AscmvcEventManagerListenerInterface extends
                                                    AscmvcBootstrapListenerInterface,
                                                    AscmvcDispatchListenerInterface,
                                                    AscmvcRenderListenerInterface,
                                                    AscmvcFinishListenerInterface {
    
}
