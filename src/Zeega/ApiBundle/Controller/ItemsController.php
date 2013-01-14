<?php

/*
* This file is part of Zeega.
*
* (c) Zeega <info@zeega.org>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Zeega\ApiBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

use Zeega\DataBundle\Entity\Item;
use Zeega\ApiBundle\Controller\ApiBaseController;

class ItemsController extends ApiBaseController
{
    public function getItemsSearchAction()
    {
        try {
            $queryParser = $this->get('zeega_query_parser');
            $query = $queryParser->parseRequest($this->getRequest()->query);

            if(isset($query["data_source"]) && $query["data_source"] == "db") {
                $results = $this->getDoctrine()->getRepository('ZeegaDataBundle:Item')->searchItems($query);
                $resultsCount = $this->getDoctrine()->getRepository('ZeegaDataBundle:Item')->getTotalItems($query);
            } else {
                $solr = $this->get('zeega_solr');
                $queryResults = $solr->search($query);
                $results = $queryResults["items"];
                $resultsCount = $queryResults["total_results"];
            }

            $user = $this->getUser();
            $editable = $this->isUserAdmin($user) || $this->isUserQuery( $query, $user );
            $itemView = $this->renderView('ZeegaApiBundle:Items:index.json.twig', array(
                'items' => $results, 
                'items_count' => $resultsCount, 
                'editable' => $editable, 
                'request' => array('query'=>$query)));
            return new Response($itemView);
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse( 422, $e->getMessage() );
        } catch (\Exception $e) {
            return parent::getStatusResponse( 500, $e->getMessage() );
        }
    }

    /**
     * Parses a url and creates a Zeega item if the url is valid and supported.
     * - Path: GET items/parser
     * - Query string parameters:
     *     - url -  URL to be parsed
     *     - Boolean  $loadChildItems  If true the child item of the item will be loaded. Should be used for large collections if only the collection description is wanted.
     * @return Array|response
     */    
    public function getItemsParserAction()
    {
        try {
            $apiKey = $this->getRequest()->query->has('api_key') ? $this->getRequest()->query->get('api_key') : null;
            $user = $this->getUser( $apiKey );           
            if( !isset($user) ) {
                return parent::getStatusResponse(401);   
            }             
            $request = $this->getRequest();
            $url  = $request->query->get('url');

            if(!isset($url)) {
                $itemView = $this->renderView('ZeegaApiBundle:Items:show.json.twig', array('item' => new Item(), 'request' => $response["details"]));
            } else {
                $loadChildren = $request->query->get('load_children');
                $loadChildren = (isset($loadChildren) && (strtolower($loadChildren) === "true" || $loadChildren === true)) ? true : false;
                $parser = $this->get('zeega_parser');
                $response = $parser->load($url, $loadChildren);
                $itemView = $this->renderView('ZeegaApiBundle:Items:index.json.twig', array('items' => $response["items"], 'request' => $response["details"]));
            }
            
            return new Response($itemView);
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse( 422, $e->getMessage() );
        } catch (\Exception $e) {
            return parent::getStatusResponse( 500, $e->getMessage() );
        }
    }

    public function getItemsParserPersistAction()
    {
        try {
            $apiKey = $this->getRequest()->query->has('api_key') ? $this->getRequest()->query->get('api_key') : null;
            $user = $this->getUser( $apiKey );           
            
            if ( !isset($user) ) {
                return parent::getStatusResponse(401);   
            }
            
            if (!$this->getRequest()->query->has('url')) {
                return parent::getStatusResponse(400, "The url parameter is mandatory.");
            }

            $url = $this->getRequest()->query->get('url');

            $parserService = $this->get('zeega_parser');
            $parser = $parserService->getParser($url);

            if ( null !== $parser && is_array($parser) ) {
                if( isset($parser["config"]) && isset($parser["config"]["is_set"]) && isset($parser["domain"]) ) {
                    $isSet = $parser["config"]["is_set"];
                    $parserId = $parser["id"];
                    $parserDomain = $parser["domain"];

                    if( True === $isSet || ($parserDomain === "dropbox.com" && $parserDomain === "facebook.com") ) {
                        $queue = $this->get('zeega_queue');
                        $taskId = $queue->enqueueTask("zeega.tasks.ingest",array($url, $user->getId()), "ingestion");
                        
                        return new Response($taskId);
                    } else {
                        $items = $parserService->loadById($parserDomain, $parserId, false, $user->getId(), array("regex_matches" => $parser["regex_matches"]));
                        
                        if( isset($items["items"]) && is_object($items["items"][0]) ) {
                            $item = $items["items"][0];
                            $item->setUser($user);
                            $item->setEnabled(true);
                            $item->setPublished(true);
                            
                            $em = $this->getDoctrine()->getEntityManager();
                            $em->persist($item);
                            $em->flush();

                            return parent::getStatusResponse(200, "Item saved");
                        }
                    }
                }
            }
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse( 422, $e->getMessage() );
        } catch (\Exception $e) {
            return parent::getStatusResponse( 500, $e->getMessage() );
        }
    }

    public function getItemsRejectedAction()
    {
        try {
            $apiKey = $this->getRequest()->query->has('api_key') ? $this->getRequest()->query->get('api_key') : null;
            $user = $this->getUser( $apiKey );
            if( !isset($user) ) {
                return parent::getStatusResponse(401);   
            } 

            $query = $this->getRequest()->query->all();
            $query["enabled"] = 0;
            $query["user"] = $user->getId();
            $query["type"] = "-project AND -Collection";
            $query["sort"] = "date-desc";

            return $this->forward('ZeegaApiBundle:Items:getItemsSearch', array(), $query);
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse( 422, $e->getMessage() );
        } catch ( \Exception $e ) {
            return parent::getStatusResponse( 500, $e->getMessage() );
        }            
    }

    public function getItemsUnmoderatedAction()
    {
        try {
            $apiKey = $this->getRequest()->query->has('api_key') ? $this->getRequest()->query->get('api_key') : null;
            $user = $this->getUser( $apiKey );
            if( !isset($user) ) {
                return parent::getStatusResponse(401);   
            } 

            $query = $this->getRequest()->query->all();
            $query["published"] = 0;
            $query["enabled"] = 1;
            $query["user"] = $user->getId();
            $query["type"] = "-project AND -Collection";
            $query["sort"] = "date-desc";

            return $this->forward('ZeegaApiBundle:Items:getItemsSearch', array(), $query);
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse( 422, $e->getMessage() );
        } catch ( \Exception $e ) {
            return parent::getStatusResponse( 500, $e->getMessage() );
        }      
    }
    
    public function getItemAction($id)
    {
        try {
            $queryParser = $this->get("zeega_query_parser");
            $query = $queryParser->parseRequest( $this->getRequest()->query );
            $recursiveResults = $query["result_type"] == "recursive" ? true : false;
            $item = null;
                 
            if( isset($query["data_source"]) && $query["data_source"] == "db" ) {
                $em = $this->getDoctrine()->getEntityManager();
                $item = $parentItem = $em->getRepository("ZeegaDataBundle:Item")->findOneByIdWithUser($id);
                if ( true === $recursiveResults ) {
                    $query = $this->getRequest()->query->all();
                    $query["collection"] = $id;
                    $query = $queryParser->parseRequest($query);
                    $queryResults = $em->getRepository('ZeegaDataBundle:Item')->searchCollectionItems($query);
                    $parentItem["child_items"] = $queryResults;
                }
            } else {
                $solr = $this->get("zeega_solr");

                $query = $this->getRequest()->query->all();
                $query["id"] = $id;
                $query = $queryParser->parseRequest($query);
                $queryResults = $solr->search($query);
                if( isset($queryResults) && count($queryResults["items"]) > 0 ) {
                    $item = $parentItem = $queryResults["items"][0];
                } else {
                    $parentItem = null;
                }
                if ( true === $recursiveResults ) {
                    $query = $this->getRequest()->query->all();
                    $query["collection"] = $id;
                    $query = $queryParser->parseRequest($query);
                    $queryResults = $solr->search($query);
                    $parentItem["child_items"] = $queryResults["items"];
                }
            }

            $user = $this->getUser();
            $editable = $this->isUserAdmin($user) || $this->isItemOwner( $item, $user );
            $itemView = $this->renderView( "ZeegaApiBundle:Items:show.json.twig", array(
                "item" => $parentItem, 
                "editable" => $editable ) );

            return new Response($itemView);
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse( 422, $e->getMessage() );
        } catch (\Exception $e) {
            return parent::getStatusResponse( 500, $e->getMessage() );
        }
    }
    
    public function getItemsAction()
    {
        try {
            $query = $this->getRequest()->query->all();
            $query["sort"] = "date-desc";
            
            return $this->forward('ZeegaApiBundle:Items:getItemsSearch', array(), $query);
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse( 422, $e->getMessage() );
        } catch (\Exception $e) {
            return parent::getStatusResponse( 500, $e->getMessage() );
        }
    }

    public function getItemItemsAction($id)
    {
        try {
            if ( !isset($id) || !is_numeric($id) ) {
                return parent::getStatusResponse( 422, "The id parameter is mandatory and must be an integer" );
            }

            $item = $this->getDoctrine()->getRepository('ZeegaDataBundle:Item')->findOneById($id);    

            if ( !isset($item) ) {
                return parent::getStatusResponse( 400, "The item with the id $id does not exist" );
            }

            $query = $this->getRequest()->query->all();        
            if($item->getMediaType() == 'Collection' && $item->getLayerType() == 'Dynamic') {
                $itemAttributes = $item->getAttributes();
                $query["user"] = $item->getUserId();
                if(isset($itemAttributes["tags"])) {
                    if(is_array($itemAttributes["tags"])) {
                        $query["tags"] = implode(" AND ", $itemAttributes["tags"]);
                    } else {
                        $query["tags"] = $itemAttributes["tags"];
                    }
                }
            } else {
                $query["collection"] = $item->getId();
            }

            return $this->forward('ZeegaApiBundle:Items:getItemsSearch', array(), $query); 
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse( 422, $e->getMessage() );
        } catch (\Exception $e) {
            return parent::getStatusResponse( 500, $e->getMessage() );
        }
    }

    public function deleteItemAction($itemId)
    {
        try {
            // parameter validation
            if ( !isset($itemId) || !is_numeric($itemId) ) {
                return parent::getStatusResponse( 422, "The item id parameter is mandatory and must be an integer" );
            }
            $em = $this->getDoctrine()->getEntityManager();
            $item = $em->getRepository('ZeegaDataBundle:Item')->find($itemId);
            if ( !isset($item) ) {
                return parent::getStatusResponse( 400, "The item with the id $itemId does not exist" );
            }
            $item->setEnabled(false);
            $item->setDateUpdated( new \DateTime("now") );
            $em->flush();

            return parent::getStatusResponse( 200 );
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse( 422, $e->getMessage() );
        } catch (\Exception $e) {
            return parent::getStatusResponse( 500, $e->getMessage() );
        }
    }

    /**
     * Deletes a child item from an item
     *
     * @return Array|response
     */    
    public function deleteItemItemAction($itemId, $childItemId)
    {
        try {
            // parameter validation
            if ( !isset($itemId) || !is_numeric($itemId) ) {
                return parent::getStatusResponse(422, "The item id parameter is mandatory and must be an integer");
            }        
            if ( !isset($childItemId) || !is_numeric($childItemId) ) {
                return parent::getStatusResponse(422, "The child item id parameter is mandatory and must be an integer");
            }            
            // get the item
            $em = $this->getDoctrine()->getEntityManager();
            $item = $em->getRepository("ZeegaDataBundle:Item")->findOneById( $itemId );

            if ( !isset($item) ) {
                return parent::getStatusResponse(400, "The item with the id $itemId does not exist");
            }            
            // authorization
            $apiKey = $this->getRequest()->query->has('api_key') ? $this->getRequest()->query->get('api_key') : null;
            $user = $this->getUser($apiKey);
            
            if ( $this->isUserAdmin($user) || $this->isItemOwner($item, $user) ) {
                // get the child item
                $childItem = $em->getRepository("ZeegaDataBundle:Item")->findOneById( $childItemId );
                if ( !isset($childItem) ) {
                    return parent::getStatusResponse(400, "The child item with the id $childItemId does not exist");
                }
                // remove the item from the collection and render the response
                $item->getChildItems()->removeElement($childItem);
                $item->setChildItemsCount($item->getChildItems()->count());
                $item->setDateUpdated(new \DateTime("now"));
                $em->flush();
                $itemView = $this->renderView('ZeegaApiBundle:Items:show.json.twig', array('item' => $item));
                
                return new Response($itemView);
            } else {
            
                return parent::getStatusResponse(403);
            }
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse(422, $e->getMessage());
        } catch (\Exception $e) {
            return parent::getStatusResponse(500, $e->getMessage());
        } 
    }

    public function postItemsAction()
    {
        try {
            // authorization
            $apiKey = $this->getRequest()->query->has('api_key') ? $this->getRequest()->query->get('api_key') : null;
            $user = $this->getUser($apiKey);
            if( !isset($user) ) {
                return parent::getStatusResponse(401);   
            }

            $requestData = $this->getRequest()->request;
            if($requestData->has('id') && $requestData->get('id') !== null && $requestData->get('child_items') && $requestData->get('child_items') !== null) {
                $requestData->set('new_items', $requestData->get('child_items'));
                $requestData->set('child_items', null);

                return $this->forward('ZeegaApiBundle:Items:putItemItems', array("itemId"=>$requestData->get('id')), array());
            }

            $itemService = $this->get('zeega.item');
            $item = $itemService->parseItem($requestData->all(), $user);
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($item);
            $em->flush();
            $itemView = $this->renderView('ZeegaApiBundle:Items:show.json.twig', array('item' => $item));

            return new Response($itemView);
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse(422, $e->getMessage());
        } catch ( \Exception $e ) {
            return parent::getStatusResponse(500, $e->getMessage());
        } 
    }
    
    // delete_items_tags  DELETE   /api/items/{itemId}/tags/{tagName}.{_format}
    public function deleteItemTagsAction($itemId, $tagName)
    {
        try {
            // authorization
            $apiKey = $this->getRequest()->query->has('api_key') ? $this->getRequest()->query->get('api_key') : null;
            $user = $this->getUser($apiKey);
            if ( !isset($user) ) {
                return parent::getStatusResponse(401);   
            }

            $em = $this->getDoctrine()->getEntityManager();
            $item = $em->getRepository('ZeegaDataBundle:Item')->find($itemId);
            if ( !isset($item) ) {
                return parent::getStatusResponse( 400, "The item with the id $itemId does not exist" );
            }

            if ( $this->isUserAdmin($user) || $this->isItemOwner($item, $user) ) {
                $tags = $item->getTags();
                if ( isset($tags) ) {
                    if ( in_array($tagName,$tags) ) {
                        unset($tags["$tagName"]);
                        $item->setTags($tags);
                        $item->setDateUpdated( new \DateTime("now") );
                        $em->persist($item);
                        $em->flush();
                    }
                }

                return new Response($itemsView);
            } else {
                return parent::getStatusResponse(403);
            }
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse(422, $e->getMessage());
        } catch ( \Exception $e ) {
            return parent::getStatusResponse(500, $e->getMessage());
        }
    }
    
    public function putItemsAction($itemId)
    {
        try {
            if ( !isset($itemId) || !is_numeric($itemId) ) {
                return parent::getStatusResponse(422, "The item id parameter is mandatory and must be an integer");
            }    
            // authorization
            $apiKey = $this->getRequest()->query->has('api_key') ? $this->getRequest()->query->get('api_key') : null;
            $user = $this->getUser($apiKey);
            if ( !isset($user) ) {
                return parent::getStatusResponse(401);   
            }

            $em = $this->getDoctrine()->getEntityManager();
            $item = $em->getRepository('ZeegaDataBundle:Item')->find($itemId);
            if (!$item) {
                return parent::getStatusResponse( 400, "The child item with the id $itemId does not exist" );
            }  

            $requestData = $this->getRequest()->request;        
            $itemService = $this->get('zeega.item');
            $itemRequestData = $requestData->all();
            if(isset($itemRequestData["child_items"])) {
                $itemRequestData["child_items"] = null;
            }
            $item = $itemService->parseItem($itemRequestData, $user, $item);
            
            if ( $this->isUserAdmin($user) || $this->isItemOwner($item, $user) ) {
                $em->persist($item);
                $em->flush();
                $editable = $this->isUserAdmin($user) || $this->isItemOwner( $item, $user );
                $itemView = $this->renderView('ZeegaApiBundle:Items:show.json.twig', array(
                    "item" => $item,
                    "editable" => $editable));

                return new Response($itemView);
            } else {
                return parent::getStatusResponse(403);
            }
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse(422, $e->getMessage());
        } catch ( \Exception $e ) {
            return parent::getStatusResponse(500, $e->getMessage());
        }    
    }
    
    public function putItemItemsAction($itemId)
    {
        try {
            // authorization
            $apiKey = $this->getRequest()->query->has('api_key') ? $this->getRequest()->query->get('api_key') : null;
            $user = $this->getUser($apiKey);
            if ( !isset($user) ) {
                return parent::getStatusResponse(401);   
            }

            $em = $this->getDoctrine()->getEntityManager();
    
            $newItems = $this->getRequest()->request->get('new_items');
            $itemsToRemoveString = $this->getRequest()->request->get('items_to_remove'); 
            if(isset($itemsToRemoveString)) {  
                $itemsToRemove = array();
                $itemsToRemove = explode(",",$itemsToRemoveString);
            }
            
            $item = $em->getRepository('ZeegaDataBundle:Item')->find($itemId);
            if ( !isset($item) ) {
                return parent::getStatusResponse( 400, "The item with the id $itemId does not exist" );
            }

            if ( $this->isUserAdmin($user) || $this->isItemOwner($item, $user) ) {
                if (isset($newItems)) {
                    $item->setDateUpdated(new \DateTime("now"));            
                    $itemService = $this->get('zeega.item');
                    foreach( $newItems as $newItem ) {
                        if( !is_array($newItem) ) {    
                            $childItem = $em->getRepository('ZeegaDataBundle:Item')->find($newItem);
                            if (!$childItem) {
                                 return parent::getStatusResponse( 400, "The child item with the id $newItem does not exist" );
                            }                            
                            $childItem->setDateUpdated(new \DateTime("now"));
                            $item->addChildItem($childItem);
                        } else {
                            $existingItem = $em->getRepository('ZeegaDataBundle:Item')->findOneBy(array(
                                "uri" => $newItem['uri'], 
                                "enabled" => 1, 
                                "user_id" => $user->getId()));

                            if(isset($existingItem) && count($existingItem) > 0) {
                                // the item is a duplicate; skip the rest of the current loop iteration and continue execution at the condition evaluation
                                continue;
                            } else {
                                $childItem = $itemService->parseItem($newItem, $user);
                                $item->addChildItem($childItem);
                            }
                        }
                    }

                    $item->setDateUpdated(new \DateTime("now"));
                    $item->setChildItemsCount($item->getChildItems()->count() + count($newItems));
                    $em->persist($item);
                    $em->flush();
                }
                
                if(isset($itemsToRemove)) {
                    foreach($itemsToRemove as $itemToRemoveId) {
                        $childItem = $em->getRepository('ZeegaDataBundle:Item')->find($itemToRemoveId);
                        if (isset($childItem))  {
                            $item->getChildItems()->removeElement($childItem);
                            $childItem->setDateUpdated(new \DateTime("now"));
                            $em->persist($childItem);
                        }
                    }
                    $item->setChildItemsCount($item->getChildItems()->count());            
                    $item->setDateUpdated(new \DateTime("now"));
                    $em->persist($item);
                    $em->flush();
                }
            }

            $itemView = $this->renderView('ZeegaApiBundle:Items:show.json.twig', array('item' => $item));
            return new Response($itemView);
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse(422, $e->getMessage());
        } catch ( \Exception $e ) {
            return parent::getStatusResponse(500, $e->getMessage());
        }      
    }
   
    // get_collection_project GET    /api/collections/{id}/project.{_format}
    public function getItemProjectAction($id)
    {
        try {
            $item = $this->getDoctrine()->getRepository('ZeegaDataBundle:Item')->findOneById($id);
            
            if(null !== $item) {
                $i=1;
                $frameOrder=array();
                $frames=array();
                $layers=array();

                $queryParser = $this->get('zeega_query_parser');
                $query = $this->getRequest()->query->all();
                $query["type"] = "-project";
                $itemAttributes = $item->getAttributes();

                if($item->getMediaType() == 'Collection' && $item->getLayerType() == 'Dynamic') {
                    if(isset($itemAttributes["tags"])) {
                        $query["user"] = $item->getUserId();
                        if(is_array($itemAttributes["tags"])) {
                            $query["tags"] = implode(" AND ", $itemAttributes["tags"]);    
                        } else {
                            $query["tags"] = $itemAttributes["tags"];
                        }
                    }
                } else {
                    $query["collection"]  = $id;
                }
                $query = $queryParser->parseRequest($query);
                
                $solr = $this->get('zeega_solr');
                $queryResults = $solr->search($query);
                $queryResults = $queryResults["items"];
                
                foreach($queryResults as $childItem) {
                    if($childItem['media_type']!='Collection' && $childItem['media_type']!='Pdf' && $childItem['media_type']!='Document') {
                        $i++;
                        $frameId = (int)$childItem['id'];
                        $frameOrder[]=$frameId;
                        $frames[]=array("id"=>$frameId,"sequence_index"=>0,"layers"=>array($i),"attr"=>array("advance"=>0));
                        
                        $layer = array("id"=>$i,"media_type"=>$childItem['media_type'],"layer_type"=>$childItem['layer_type']);
                        $layer["type"] = $layer["media_type"];
                        if(isset($childItem['text'])) {
                            $layer["text"] = $childItem['text'];
                        }

                        $layer["attr"] = array();
                        $layer["attr"]["user_id"] = $childItem['user_id'];
                        $layer["attr"]["uri"] = $childItem['uri'];
                        $layer["attr"]["attribution_uri"] =$childItem['attribution_uri'];
                        
                        if(isset($childItem['title_i'])) {
                            $layer["attr"]["title"] = $childItem['title_i'];
                        }

                        if(isset($childItem['description_i'])) {
                            $layer["attr"]["description"] = $childItem['description_i'];
                        }

                        if(isset($childItem['thumbnail_url'])) {
                            $layer["attr"]["thumbnail_url"] = $childItem['thumbnail_url'];
                        }

                        if(isset($childItem['media_creator_username'])) {
                            $layer["attr"]["media_creator_username"] = $childItem['media_creator_username'];
                        }

                        if(isset($childItem['media_creator_realname'])) {
                            $layer["attr"]["media_creator_realname"] = $childItem['media_creator_realname'];
                        }

                        if(isset($childItem['media_date_created'])) {
                            $layer["attr"]["media_date_created"] = $childItem['media_date_created'];
                        }

                        if(isset($childItem['date_created'])) {
                            $layer["attr"]["date_created"] = $childItem['date_created'];
                        }

                        if(isset($childItem['tags'])) {
                            $layer["attr"]["tags"] = $childItem['tags'];
                        }

                        if(isset($childItem['media_geo_latitude'])) {
                            $layer["attr"]["media_geo_latitude"] = $childItem['media_geo_latitude'];
                        }

                        if(isset($childItem['media_geo_longitude'])) {
                            $layer["attr"]["media_geo_longitude"] = $childItem['media_geo_longitude'];
                        }

                        if(isset($childItem['archive'])) {
                            $layer["attr"]["archive"] = $childItem['archive'];
                        }
                        
                        if(isset($childItem['description'])) {
                            $layer["attr"]["description"] = $childItem['description'];
                        }

                        $layers[] = $layer;
                    }
                }               
                
                $creator = $item->getMediaCreatorRealname();
                if(!isset($creator)) {
                    $creator = $item->getMediaCreatorUsername();
                }

                $project = array("id"=>$item->getId(),
                      "title"=>$item->getTitle(),
                      "estimated_time"=>"Some time", 
                      "authors"=>$creator,
                      "sequences"=>array(array('id'=>1,'frames'=>$frameOrder,"title"=>'none', 'attr'=>array("persistLayers"=>array()))),
                      'frames'=>$frames,
                      'layers'=>$layers,
                    );

                return new Response(json_encode($project));
            }
        } catch ( \BadFunctionCallException $e ) {
            return parent::getStatusResponse(422, $e->getMessage());
        } catch ( \Exception $e ) {
            return parent::getStatusResponse(500, $e->getMessage());
        }   
    }
}
