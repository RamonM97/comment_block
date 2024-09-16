<?php

namespace Drupal\comment_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Bloque Customizado 'Comment Block'.
 *
 * @Block(
 *   id = "comment_block",
 *   admin_label = @Translation("Comment Block"),
 * )
 */
class CommentBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() { //Método principal para construir el bloque
    
    $user = \Drupal::routeMatch()->getParameter('user'); //Obtiene el usuario si es una página de perfil

    if ($user instanceof User) { //Si es un perfil de usuario válido extrae su ID
      $uid = $user->id();
    }
    else {
      return []; //Si no lo es, no muestra nada en esa página
    }

    $output = []; //Array de salida

    $connection = Database::getConnection(); //Conectamos con la BBDD

    //Consulta para obtener el total de comentarios del usuario
    $query = $connection->select('comment_field_data', 'c')
      ->condition('uid', $uid) //La uid tiene que coincidir la del usuario
      ->countQuery() //Contamos el número de resultados
      ->execute() //Ejecutamos la consulta
      ->fetchField(); //Obtener el único resultado de la consulta
    $output['total_comments'] = $query; //Guardamos en la salida el total de comentarios

    //Vamos a obtener los últimos cinco comentarios y el cuerpo del comentario
    $query = $connection->select('comment_field_data', 'c')
      ->fields('c', ['cid', 'entity_id']) //Extraemos los cid y entity_id cuya uid sea la del usuario
      ->condition('uid', $uid)
      ->orderBy('created', 'DESC') //Ordenado en base a la fecha de creación
      ->range(0, 5); //Solo los últimos 5

    //Usamos el cid extraido para buscar en la tabla que contiene el cuerpo del comentario
    $query->join('comment__comment_body', 'cb', 'c.cid = cb.entity_id'); //Tiene que coincidir el c.cid = cb.entity_id
    $query->addField('cb', 'comment_body_value', 'comment_body'); //Sacamos el cuerpo del comentario con alias coment_body

    $result = $query->execute(); //Ejecutamos toda la consulta

    $comments = []; //Array donde guardaremos los comentarios + el nodo de cada comentario
    $total_words = 0; //Inicializamos el contador de palabras global de todos los comentarios

    foreach ($result as $comment) { //Recorremos el resultado de la consulta, con alias $comment para conseguir comentarios + nodos

      $comment_body = strip_tags($comment->comment_body); //Recogemos el comentario de la consulta sin tags html
      $word_count = str_word_count($comment_body); //Contamos el número de palabras del comentario
      $total_words += $word_count; //Lo sumamos al contador global de palabras

      //Vamos a obtener el título del nodo en el que ha comentado
      //Usamos el entity_id de 'comment_field_data' extraido anteriormente en la consulta
      $node = Node::load($comment->entity_id); //Node::load es la API de drupal para cargar un node a partir de su ID    

      $comments[] = [ //Guardamos en el array ya inicializado el comentario y nodo nuevo
        'comment' => isset($comment->comment_body) ? strip_tags($comment->comment_body) : 'Sin texto de comentario',
        'node_title' => $node ? $node->getTitle() : 'Sin título de nodo',
      ];
    }

    $output['recent_comments'] = $comments; //Guardamos en la salida el array de comentarios y sus nodos
    $output['total_words'] = $total_words - 1; //Guardamos en la salida la suma total de palabras de todos los comentarios

    return [ //Vamos a devolver una tabla html con los resultados y con estilos
      '#type' => 'markup',
      '#markup' => '
        <table class="custom-comment-table">
          <tr>
            <th>Total de comentarios:</th>
            <td>' . $output['total_comments'] . '</td>
          </tr>
          <tr>
            <th>Número total de palabras:</th>
            <td>' . $output['total_words'] . '</td>
          </tr>
          <tr>
            <th>Últimos 5 comentarios:</th>
            <td>
              <table class="custom-comment-table">
                <tr>
                  <th>Comentario</th>
                  <th>Título del nodo</th>
                </tr> 
                ' . (isset($output['recent_comments']) ? implode('', array_map(function ($comment) {
                    return '<tr><td>' . htmlspecialchars($comment['comment']) . '</td><td>' . htmlspecialchars($comment['node_title']) . '</td></tr>';
                  }, $output['recent_comments'])) :
                  '<tr><td>No hay comentarios</td></tr>') . '
              </table>
            </td>
          </tr>
        </table>',
      '#attached' => [
        'library' => [
          'comment_block/comment_block_styles',
        ],
      ],
    ];
    //Con array_map aplicamos la función para cada elemento del array devolviendonos filas html
    //Con implode las juntamos sin nada "" para formar una cadena de texto html
  }
  /**
   * {@inheritdoc}
   */
    public function getCacheMaxAge() {
      return 0; //Evitar cacheado y que asegurar que se aplique corretamente solo en las páginas de perfil
  }
}
