<?php
namespace Blog\Controller\Admin;

use Blog\Controller\AppController;
use Cake\Cache\Cache;
use Cake\Event\Event;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\Query;
use Cake\Utility\Hash;

/**
 * Categories Controller
 *
 * @property \App\Model\Table\CategoriesTable $Categories
 * @property \App\Controller\Component\SaveCloseComponentComponent $SaveClose
 */
class CategoriesController extends AppController
{

    public function initialize(){
        parent::initialize();
//        $this->loadComponent('Security');
        $this->loadComponent('Paginator');
        $this->loadComponent('SaveClose');

    }

    public function beforeFilter(Event $event){
        parent::beforeFilter($event);
        $this->Security->setConfig('unlockedActions', ['actions','online']);

    }

    public function isAuthorized($user)
    {
        if(in_array($user['role'],['admin','superadmin'])){
            return true;
        }
        return false;
    }

    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index()
    {

        if($this->request->getSession()->check('Temp')){
            $this->request->getSession()->delete('Temp');
        }

        $this->paginate = [
            'limit' =>25,
            'fields'=>['id','name','slug', 'online','position','parent_id'],
            'finder' => [
                'parent' => ['parent_id' => $this->request->getQuery('parent_id')]
            ],
            'contain'=>['ChildCategories'=>[
                'fields'=>['id','parent_id']
            ]],
            'order'=>['Categories.position'=>'ASC']
        ];
        //TODO Voir pour la recherche, pb avec finder
        if($this->request->getQuery('search')){
            foreach($this->request->getQuery() as $k =>  $v){
                if(!empty($v) &&  $k != 'page' && $k != 'sort' && $k != 'direction' && $k != 'search'){
                    $this->paginate['conditions']['Categories.'.$k.' LIKE'] =  '%'.trim($v).'%';
                }
            }
        }


        try{
            $categories = $this->paginate($this->Categories);
        } catch(NotFoundException $e){
            $this->redirect(array('action'=>'index'));
        }

        if($this->request->getQuery('parent_id')){
            $parent = $this->Categories->find('path',['for'=>$this->request->getQuery('parent_id'),'fields'=>['id','parent_id','name']])
                ->toArray();
            $parent = end($parent);
            $this->set('parent', $parent);
        }

        $this->set(compact('categories'));

    }

    /**
     * Add method
     *
     * @param null|string $type
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */


    public function add($type = 'category')
    {

        $category = $this->Categories->newEntity();
        if ($this->request->is('post')) {
            $category = $this->Categories->patchEntity($category, $this->request->getData());
            if(!empty($this->request->getData('parent_id'))){
                $category->parent = $this->Categories->getParentSlug($this->request->getData('parent_id'));
            }

            if ($this->Categories->save($category)) {
                $this->Flash->success('Le contenu a bien été enregistré.');
                $this->SaveClose->redirect($category->id);
            } else {
                $this->Flash->error('Merci de vérifier les informations saisies');
            }

        }
        if (!$this->request->getSession()->check('Temp')) {
            $this->request->getSession()->write('Temp', $this->referer());
        }
        $parentCategories = $this->Categories->listCategories();
        $this->set(compact('category', 'parentCategories'));

    }

    /**
     * Edit method
     *
     * @param null $id
     * @return \Cake\Network\Response|void
     */
    public function edit($id = null)
    {
        $category = $this->Categories->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {

            $category = $this->Categories->patchEntity($category, $this->request->getData());
            if(!empty($this->request->getData('parent_id'))){
                $category->parent = $this->Categories->getParentSlug($this->request->getData('parent_id'));
            }
            if ($this->Categories->save($category)) {
                $this->Flash->success('La catégorie a bien été mise à jour.');
                $this->SaveClose->redirect($category->id);

            } else {
                $this->Flash->error('Merci de verifier les informations saisies.');
            }

        }
        if(!$this->request->getSession()->check('Temp')){
            $this->request->getSession()->write('Temp',$this->referer());
        }

        $parentCategories = $this->Categories->listCategories($category->id);

        $this->set(compact('category', 'parentCategories'));




    }
    
    /**
     * Delete method
     *
     * @param string|null $id Page id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $page = $this->Categories->get($id);
        if ($this->Categories->delete($page)) {
            $this->Flash->success('La categorie a bien été supprimée');
        } else {
            $this->Flash->error('Impossible de supprimer la catégorie.');
        }
        return $this->redirect(['action' => 'index']);
    }
    /**
     * @param $id
     */
    public function confirm($id){
        $this->viewBuilder()->setLayout('ajax');
        $this->set('id',$id);

    }

    public function online()
    {
        if ($this->request->is('ajax')) {
            $category = $this->Categories->find()->where(['Categories.id' => $this->request->getData('id')])->first();
            $category->online = (!$category->online) ? true : false;
            $this->Categories->save($category);
        }
        exit();
    }

    public function actions(){

        if($this->request->is('ajax')){

            // Force le controller à rendre une réponse JSON.
            // Et définit le type de réponse de la requete AJAX
            $this->RequestHandler->renderAs($this, 'json');
            $this->response->withType('application/json');
            $this->viewBuilder()->setLayout('ajax');

            $data = $this->request->getData();
            $statut = 0;
            if(empty($data['action']) || empty($data['datas'])){
                $statut = 0;
            }
            $pageIds = Hash::extract($data['datas'],'{n}.value');

            if($data['action'] == 'offline'){
                if($this->Categories->updateAll(
                    array('Categories.online' => false),
                    array('Categories.id IN' => $pageIds)
                )){
                    $statut = 1;
                }
            }

            if($data['action'] == 'online'){
                if($this->Categories->updateAll(
                    array('Categories.online' => true),
                    array('Categories.id IN' => $pageIds)
                )){
                    $statut = 1;
                }

            }

            if($data['action'] == 'delete'){
                foreach($pageIds as $pId){
                    $pageId = $this->Categories->find()
                        ->select(['id'])
                        ->where(['Categories.id'=>$pId])
                        ->first();
                    if($pageId){
                        $this->Categories->delete($pageId);
                    }
                }
                $statut = 1;

            }
            $this->set('statut', json_encode($statut));
        } else {
            throw new MethodNotAllowedException('Cette action n\'est pas autorisée.', 403);
        }
    }

    /**
     * @param null $id
     * @param null $menu_id
     */
    public function movedown($id = null,$menu_id = null) {
        Cache::deleteMany(['categories'],'nav');
        $this->Categories->to_down($id,$menu_id);
        $this->redirect($this->referer());
    }

    /**
     * @param null $id
     * @param null $menu_id
     */
    public function moveup($id = null,$menu_id = null) {
        Cache::deleteMany(['categories'],'nav');
        $this->Categories->to_up($id,$menu_id);
        $this->redirect($this->referer());
    }

    public function order() {
        $data = $this->request->getData();
        Cache::deleteMany(['categories'],'nav');
        if(!$this->Categories->position($data)){
            $this->Flash->error('La position demandée est supérieure au nombre de lignes','flash', array('class'=>'alert'));
        }

        $this->redirect($this->referer());
    }


}
